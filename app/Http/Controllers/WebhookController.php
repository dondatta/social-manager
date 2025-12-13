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

        // 1. Story Reply (message with a reference to a story)
        // OR 4. Story Mention (sometimes comes as a message or mention, checking logic)
        
        if (isset($event['message'])) {
            $message = $event['message'];
            
            // Check for Story Reply
            // Usually has 'reply_to' => ['story' => ...] but structure varies.
            // Simplified check: if it's a message response to a story
            
            // Actually, Story Mentions often come as a message with an attachment or specific type.
            // Let's implement generic Message Handler first
            
            // Story Reply Check: often normal text but context might be available?
            // "User replies to any of our current Stories" -> The webhook event usually indicates source.
            
            // For now, let's look for specific signals.
            
            // If it is a simple text message, ignore unless we want a general chatbot.
            // But we need "Story Reply".
            
            // NOTE: Accurate detection of "Story Reply" vs "Normal DM" requires checking 'reply_to' field in some API versions,
            // or checking if the conversation started from a story.
            
            // 4. Story Mention
            // When a user mentions you in their story, you get a message saying "Mentioned you in their story" + attachment.
            
            if (isset($message['attachments'])) {
                // Check if it's a story mention
                // (Logic to be refined based on actual payload structure for Story Mentions)
                 $this->processStoryMention($senderId);
            } else {
                 // Assume generic reply for now or implement "Story Reply" detection if possible via 'reply_to'
                 // Verify "Story Reply" vs generic DM:
                 // In V21.0, there isn't a guaranteed "is_story_reply" flag in the basic webhook without looking at context.
                 // However, we can check if the user has a recent story interaction or just treat all incoming DMs as potentially replying if configured.
                 
                 // User Requirement 1: "User replies to any of our current Stories."
                 // This is tricky. Let's look for `reply_to` field if it exists.
                 if (isset($message['reply_to']['story'])) {
                      $this->processStoryReply($senderId);
                 }
            }
        }
    }

    protected function handleChangeEvent($event)
    {
        $field = $event['field'] ?? null;
        $value = $event['value'] ?? [];

        // 2. New Follower Welcome
        // Usually field = 'feed'? No, actually, for followers, we need the Graph API or "Instagram User" webhooks.
        // There isn't a direct "new_follower" webhook for Instagram Business accounts in the same way as simple events.
        // Wait, "Instagram Basic Display" had it? No.
        // "Webhooks for Instagram Graph API" -> "comments", "mentions", "story_insights".
        // There is NO direct "follow" webhook in the official Instagram Graph API for Business.
        // Workaround: Poll /insights or use third-party? The user said "Exclusive Official API".
        // RE-CHECK: Is there a 'followed' event?
        // Answer: No native 'new_follower' webhook for IG Business. 
        // Many solutions poll the followers list (which is rate limited). 
        // OR maybe "User follows our Instagram account" is not fully possible via strictly "Webhooks" instantaneously.
        // BUT, maybe they mean "messages" with a "started following" context? Unlikely.
        
        // Let's assume for now we might handle it via a different trigger or acknowledge the limitation.
        // Actually, let's implement the COMMENT handler which is definitely supported.

        // 3. Comment-to-DM
        if ($field === 'comments') {
            // value contains media_id, text, from, id...
            $this->processComment($value);
        }
        
        // 4. Mentions (in Stories or Posts)
        if ($field === 'mentions') {
            // Someone mentioned the page
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
        
        if (!$userId) return;

        $keyword = AutomationSetting::where('key', 'comment_keyword')->value('value');
        if ($keyword && stripos($text, $keyword) !== false) {
            $this->triggerAutomation($userId, 'comment_dm');
        }
    }

    protected function processMention($data)
    {
        // $data usually contains 'media_id', 'comment_id' etc.
        // We need the user_id.
        // "User mentions our account in their own Story."
        // Story mentions often come via Messaging webhook (processed above) or Mention webhook.
        // If it comes here:
        // $this->triggerAutomation($userId, 'story_mention');
    }

    protected function triggerAutomation($userId, $type)
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
        $success = $this->instagramService->sendDm($userId, $message);

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
