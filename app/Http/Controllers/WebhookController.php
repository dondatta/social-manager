<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\InstagramService;
use App\Models\AutomationSetting;
use App\Models\AutomationLog;
use App\Models\AutomationCooldown;
use App\Jobs\SendWelcomeDmJob;

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
            
            // 4. Story Mention
            // When a user mentions you in their story, you receive a message with an attachment of type 'story_mention'
            if (isset($message['attachments'])) {
                foreach ($message['attachments'] as $attachment) {
                    if (($attachment['type'] ?? '') === 'story_mention') {
                        $this->processStoryMention($senderId);
                        return;
                    }
                }
            }
            
            // 1. Story Reply
            // Check if the message is a reply to a story
            if (isset($message['reply_to']['story'])) {
                 $this->processStoryReply($senderId);
                 return;
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

    protected function processStoryReply($userId)
    {
        $this->triggerAutomation($userId, 'story_reply');
    }

    protected function processStoryMention($userId)
    {
        $this->triggerAutomation($userId, 'story_mention');
    }

    protected function processComment($data)
    {
        $text = $data['text'] ?? '';
        $userId = $data['from']['id'] ?? null;
        $commentId = $data['id'] ?? null;
        
        if (!$userId || !$commentId) return;

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
        $firstName = $profile['first_name'] ?? 'there';
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
