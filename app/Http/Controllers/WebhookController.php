<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\InstagramService;
use App\Models\AutomationSetting;
use App\Models\AutomationLog;
use App\Models\AutomationCooldown;
use App\Models\Message;
use App\Jobs\SendWelcomeDmJob;
use App\Jobs\SyncToHubspotJob;
use App\Jobs\FetchUserProfileJob;
use Filament\Notifications\Notification;
use App\Models\User;

class WebhookController extends Controller
{
    protected $instagramService;

    public function __construct(InstagramService $instagramService)
    {
        $this->instagramService = $instagramService;
    }

    public function handle(Request $request)
    {
        $payload = $request->all();
        // Log::info('Instagram Webhook Received', $payload); // Debug log

        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            $messaging = $entry['messaging'] ?? [];

            // Handle 'messaging' events (Direct Messages, Story Replies)
            foreach ($messaging as $messageEvent) {
                $this->handleMessagingEvent($messageEvent);
            }

            // Handle 'changes' events (Comments, Feed)
            foreach ($changes as $changeEvent) {
                $this->handleChangeEvent($changeEvent);
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    protected function handleMessagingEvent($event)
    {
        $senderId = $event['sender']['id'] ?? null;
        if (!$senderId) return;

        if (isset($event['message'])) {
            $message = $event['message'];
            $messageText = $message['text'] ?? '';
            
            // Try to get username and profile picture synchronously (for immediate display)
            // If it fails, we'll fetch it asynchronously later
            $profile = $this->instagramService->getUserProfile($senderId);
            $username = ($profile && isset($profile['username'])) ? $profile['username'] : null;
            // Try both field names for profile picture
            $profilePic = null;
            if ($profile) {
                $profilePic = $profile['profile_picture_url'] ?? $profile['profile_pic'] ?? null;
            }
            
            // 4. Story Mention
            // When a user mentions you in their story, you receive a message with an attachment of type 'story_mention'
            if (isset($message['attachments'])) {
                foreach ($message['attachments'] as $attachment) {
                    if (($attachment['type'] ?? '') === 'story_mention') {
                        // Save message
                        $message = Message::create([
                            'instagram_user_id' => $senderId,
                            'instagram_username' => $username,
                            'profile_picture_url' => $profilePic,
                            'message_type' => 'story_mention',
                            'message_text' => $messageText ?: 'Story mention',
                            'raw_payload' => $event,
                        ]);
                        // Fetch profile asynchronously if we don't have it
                        if (!$username || !$profilePic) {
                            FetchUserProfileJob::dispatch($message->id)->delay(now()->addSeconds(5));
                        }
                        $this->processStoryMention($senderId, $messageText);
                        return;
                    }
                }
            }
            
            // 1. Story Reply
            // Check if the message is a reply to a story
            if (isset($message['reply_to']['story'])) {
                // Save message
                $message = Message::create([
                    'instagram_user_id' => $senderId,
                    'instagram_username' => $username,
                    'profile_picture_url' => $profilePic,
                    'message_type' => 'story_reply',
                    'message_text' => $messageText ?: 'Story reply',
                    'raw_payload' => $event,
                ]);
                // Fetch profile asynchronously if we don't have it
                if (!$username || !$profilePic) {
                    FetchUserProfileJob::dispatch($message->id)->delay(now()->addSeconds(5));
                }
                $this->processStoryReply($senderId, $messageText);
                return;
            }
            
            // Regular DM
            if ($messageText) {
                // Save message
                $message = Message::create([
                    'instagram_user_id' => $senderId,
                    'instagram_username' => $username,
                    'profile_picture_url' => $profilePic,
                    'message_type' => 'dm',
                    'message_text' => $messageText,
                    'raw_payload' => $event,
                ]);
                
                // Fetch profile asynchronously if we don't have it
                if (!$username || !$profilePic) {
                    FetchUserProfileJob::dispatch($message->id)->delay(now()->addSeconds(5));
                }
                
                // Sync to HubSpot (if configured)
                if ($username) {
                    SyncToHubspotJob::dispatch($senderId, $username, $messageText, 'Instagram DM');
                }
            }
        }
    }

    protected function handleChangeEvent($event)
    {
        $field = $event['field'] ?? null;
        $value = $event['value'] ?? [];

        // 3. Comment-to-DM
        if ($field === 'comments') {
            $this->processComment($value);
        }
        
        // 4. Mentions (in Posts/Comments)
        if ($field === 'mentions') {
            $this->processMention($value);
        }
    }

    protected function processStoryReply($userId, $messageText = '')
    {
        $profile = $this->instagramService->getUserProfile($userId);
        $username = ($profile && isset($profile['username'])) ? $profile['username'] : $userId;
        $this->notifyAdmins('New Story Reply', "User @$username replied to your story: \"$messageText\"");
        $this->triggerAutomation($userId, 'story_reply');
    }

    protected function processStoryMention($userId, $messageText = '')
    {
        $profile = $this->instagramService->getUserProfile($userId);
        $username = ($profile && isset($profile['username'])) ? $profile['username'] : $userId;
        $this->notifyAdmins('New Story Mention', "User @$username mentioned you in their story: \"$messageText\"");
        $this->triggerAutomation($userId, 'story_mention');
    }

    protected function processComment($data)
    {
        $text = $data['text'] ?? '';
        $userId = $data['from']['id'] ?? null;
        $commentId = $data['id'] ?? null;
        $mediaId = $data['media']['id'] ?? null;
        
        if (!$userId || !$commentId) return;

        // Try to extract username from webhook payload first (comments sometimes include it)
        $username = $data['from']['username'] ?? null;
        $profilePic = null;
        
        // If not in payload, try API call
        if (!$username) {
            $profile = $this->instagramService->getUserProfile($userId);
            $username = ($profile && isset($profile['username'])) ? $profile['username'] : null;
            // Try both field names for profile picture
            if ($profile) {
                $profilePic = $profile['profile_picture_url'] ?? $profile['profile_pic'] ?? null;
            }
        }

        // Save message
        $message = Message::create([
            'instagram_user_id' => $userId,
            'instagram_username' => $username,
            'profile_picture_url' => $profilePic,
            'message_type' => 'comment',
            'message_text' => $text,
            'media_id' => $mediaId,
            'comment_id' => $commentId,
            'raw_payload' => $data,
        ]);
        
        // Fetch profile asynchronously if we don't have it
        if (!$username || !$profilePic) {
            FetchUserProfileJob::dispatch($message->id)->delay(now()->addSeconds(5));
        }

        $displayName = $username ? "@$username" : $userId;
        $this->notifyAdmins('New Comment', "User $displayName commented: \"$text\"");

        // Sync to HubSpot (if configured)
        if ($text && $username) {
            SyncToHubspotJob::dispatch($userId, $username, $text, 'Instagram Comment');
        }

        $keyword = AutomationSetting::where('key', 'comment_keyword')->value('value');
        
        // If no keyword is set, maybe trigger for all comments? 
        // Usually safer to require a keyword to avoid spam.
        if ($keyword && stripos($text, $keyword) !== false) {
            $this->triggerAutomation($userId, 'comment_dm', $commentId);
        }
    }

    protected function processMention($data)
    {
        // Handle Post/Comment mentions here if needed
        // For now, Story Mentions are handled in handleMessagingEvent
        $mediaId = $data['media_id'] ?? null;
        $userId = $data['from']['id'] ?? null;
        $text = $data['text'] ?? 'Mention';
        
        if ($userId) {
            // Get username and profile picture
            $profile = $this->instagramService->getUserProfile($userId);
            $username = ($profile && isset($profile['username'])) ? $profile['username'] : null;
            // Try both field names for profile picture
            $profilePic = null;
            if ($profile) {
                $profilePic = $profile['profile_picture_url'] ?? $profile['profile_pic'] ?? null;
            }

            // Save message
            $message = Message::create([
                'instagram_user_id' => $userId,
                'instagram_username' => $username,
                'profile_picture_url' => $profilePic,
                'message_type' => 'mention',
                'message_text' => $text,
                'media_id' => $mediaId,
                'raw_payload' => $data,
            ]);
            
            // Fetch profile asynchronously if we don't have it
            if (!$username || !$profilePic) {
                FetchUserProfileJob::dispatch($message->id)->delay(now()->addSeconds(5));
            }

            $displayName = $username ? "@$username" : $userId;
            $this->notifyAdmins('New Mention', "User $displayName mentioned you in a post/comment (Media ID: $mediaId)");
        }
    }

    protected function notifyAdmins(string $title, string $body)
    {
        try {
            $admins = User::all();
            
            Notification::make()
                ->title($title)
                ->body($body)
                ->info() // or success(), warning()
                ->sendToDatabase($admins);
                
        } catch (\Exception $e) {
            Log::error('Failed to send admin notification: ' . $e->getMessage());
        }
    }

    protected function triggerAutomation($userId, $type, $commentId = null)
    {
        // Check Cooldown
        $cooldown = AutomationCooldown::where('instagram_user_id', $userId)
            ->where('action_type', $type)
            ->where('expires_at', '>', now())
            ->exists();

        if ($cooldown) {
            Log::info("Cooldown active for user $userId type $type");
            return;
        }

        // Get Template
        $templateKey = $type . '_template';
        $template = AutomationSetting::where('key', $templateKey)->value('value');
        
        if (!$template) {
            Log::warning("No template found for $type");
            return;
        }

        // Personalization
        $profile = $this->instagramService->getUserProfile($userId);
        $firstName = ($profile && isset($profile['first_name'])) ? $profile['first_name'] : 'there';
        $message = str_replace('{first_name}', $firstName, $template);

        // Send DM
        // For comment_dm, we try to send a Private Reply to the comment if we have a comment ID.
        // However, standard DM is often preferred for "Welcome". 
        // Using 'comment_id' sends a "Private Reply" which is a specific type of message linked to the comment.
        
        $isCommentReply = ($type === 'comment_dm' && $commentId);
        $recipient = $isCommentReply ? $commentId : $userId;

        $success = $this->instagramService->sendDm($recipient, $message, $isCommentReply);

        // Log
        AutomationLog::create([
            'instagram_user_id' => $userId,
            'action_type' => $type,
            'status' => $success ? 'success' : 'failed',
            'error_message' => $success ? null : 'Failed to send DM via API',
        ]);

        // Set Cooldown
        if ($success) {
            AutomationCooldown::create([
                'instagram_user_id' => $userId,
                'action_type' => $type,
                'expires_at' => now()->addHours(12),
            ]);
        }
    }
}
