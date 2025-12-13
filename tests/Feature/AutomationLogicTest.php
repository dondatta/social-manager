<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\AutomationSetting;
use App\Models\AutomationCooldown;
use App\Services\InstagramService;
use Illuminate\Support\Facades\Config;

class AutomationLogicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Settings
        AutomationSetting::create(['key' => 'story_reply_template', 'value' => 'Thanks for replying, {first_name}!']);
        AutomationSetting::create(['key' => 'comment_keyword', 'value' => 'GUIDE']);
        AutomationSetting::create(['key' => 'comment_dm_template', 'value' => 'Here is your guide, {first_name}!']);
        
        Config::set('services.instagram.access_token', 'test_token');
        Config::set('services.instagram.verify_token', 'test_verify_token');
        Config::set('services.instagram.app_secret', 'test_secret');
    }

    public function test_story_reply_triggers_dm()
    {
        Http::fake([
            'graph.facebook.com/*/me/messages' => Http::response(['recipient_id' => '123', 'message_id' => '456'], 200),
            'graph.facebook.com/*' => Http::response(['first_name' => 'John'], 200), // Profile mock
        ]);

        $payload = [
            'object' => 'instagram',
            'entry' => [[
                'id' => 'page_id',
                'messaging' => [[
                    'sender' => ['id' => 'user_123'],
                    'recipient' => ['id' => 'page_id'],
                    'message' => [
                        'mid' => 'mid_123',
                        'text' => 'Cool story!',
                        'reply_to' => ['story' => ['url' => '...']]
                    ]
                ]]
            ]]
        ];

        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');

        $response = $this->postJson('/webhooks/instagram', $payload, [
            'X-Hub-Signature-256' => $signature
        ]);

        $response->assertStatus(200);

        // Assert DM sent
        Http::assertSent(function ($request) {
            return $request->url() == 'https://graph.facebook.com/v21.0/me/messages' &&
                   $request['recipient']['id'] == 'user_123' &&
                   str_contains($request['message']['text'], 'Thanks for replying, John!');
        });

        // Assert Cooldown Created
        $this->assertDatabaseHas('automation_cooldowns', [
            'instagram_user_id' => 'user_123',
            'action_type' => 'story_reply'
        ]);
    }

    public function test_comment_keyword_triggers_dm()
    {
        Http::fake([
            'graph.facebook.com/*/me/messages' => Http::response(['recipient_id' => '123', 'message_id' => '456'], 200),
            'graph.facebook.com/*' => Http::response(['first_name' => 'Jane'], 200),
        ]);

        $payload = [
            'object' => 'instagram',
            'entry' => [[
                'id' => 'page_id',
                'changes' => [[
                    'field' => 'comments',
                    'value' => [
                        'from' => ['id' => 'user_456', 'username' => 'jane'],
                        'text' => 'Can I get the GUIDE please?',
                        'id' => 'comment_123'
                    ]
                ]]
            ]]
        ];

        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');

        $response = $this->postJson('/webhooks/instagram', $payload, [
            'X-Hub-Signature-256' => $signature
        ]);

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            // Check if it's the message endpoint
            if ($request->url() != 'https://graph.facebook.com/v21.0/me/messages') {
                return false;
            }
            return $request['recipient']['id'] == 'user_456' &&
                   str_contains($request['message']['text'], 'Here is your guide, Jane!');
        });
    }

    public function test_cooldown_prevents_duplicate_dm()
    {
        // Set cooldown
        AutomationCooldown::create([
            'instagram_user_id' => 'user_123',
            'action_type' => 'story_reply',
            'expires_at' => now()->addHours(1)
        ]);

        Http::fake();

        $payload = [
            'object' => 'instagram',
            'entry' => [[
                'messaging' => [[
                    'sender' => ['id' => 'user_123'],
                    'message' => [
                        'text' => 'Another reply',
                        'reply_to' => ['story' => []]
                    ]
                ]]
            ]]
        ];
        
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');

        $this->postJson('/webhooks/instagram', $payload, [
             'X-Hub-Signature-256' => $signature
        ]);

        Http::assertNotSent(function ($request) {
            return $request['recipient']['id'] == 'user_123';
        });
    }
}
