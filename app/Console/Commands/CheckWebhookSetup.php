<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckWebhookSetup extends Command
{
    protected $signature = 'instagram:check-webhook';
    protected $description = 'Check Instagram webhook configuration and setup';

    public function handle()
    {
        $this->info('Checking Instagram Webhook Setup...');
        $this->newLine();

        // Check access token
        $accessToken = config('services.instagram.access_token');
        $pageId = config('services.instagram.page_id');
        $appSecret = config('services.instagram.app_secret');

        $this->info('1. Configuration Check:');
        $this->table(
            ['Setting', 'Status', 'Value'],
            [
                ['IG_ACCESS_TOKEN', $accessToken ? '✓ Set' : '✗ Missing', $accessToken ? substr($accessToken, 0, 20) . '...' : 'Not found'],
                ['IG_PAGE_ID', $pageId ? '✓ Set' : '✗ Missing', $pageId ?: 'Not found'],
                ['IG_APP_SECRET', $appSecret ? '✓ Set' : '✗ Missing', $appSecret ? substr($appSecret, 0, 10) . '...' : 'Not found'],
            ]
        );

        if (!$accessToken || !$pageId) {
            $this->error('Missing required configuration!');
            $this->info('Run: php artisan instagram:get-page-token');
            return 1;
        }

        // Check webhook URL
        $this->newLine();
        $this->info('2. Webhook URL:');
        $webhookUrl = url('/webhooks/instagram');
        $this->line('Your webhook URL: ' . $webhookUrl);
        $this->warn('⚠️ Make sure this URL is accessible from the internet!');
        $this->line('   For local development, use:');
        $this->line('   - Expose: expose share http://social-manager.test');
        $this->line('   - ngrok: ngrok http 80');
        $this->line('   - Or deploy to a server with a public URL');

        // Check if webhook is subscribed
        $this->newLine();
        $this->info('3. Checking Webhook Subscriptions...');
        
        try {
            $response = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$pageId}/subscribed_apps");

            if ($response->successful()) {
                $data = $response->json();
                $this->info('✓ Webhook subscriptions retrieved');
                
                if (isset($data['data']) && !empty($data['data'])) {
                    $this->table(
                        ['App ID', 'Subscribed Fields'],
                        array_map(function($app) {
                            return [
                                $app['id'] ?? 'N/A',
                                implode(', ', $app['subscribed_fields'] ?? [])
                            ];
                        }, $data['data'])
                    );
                } else {
                    $this->warn('⚠️ No webhook subscriptions found!');
                    $this->info('You need to subscribe to webhooks in Facebook App Dashboard.');
                }
            } else {
                $error = $response->json();
                $this->error('✗ Failed to check webhook subscriptions');
                $this->error($error['error']['message'] ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            $this->error('Error checking webhooks: ' . $e->getMessage());
        }

        // Instructions
        $this->newLine();
        $this->info('4. To Set Up Webhooks:');
        $this->line('1. Go to: https://developers.facebook.com/apps/');
        $this->line('2. Select your app');
        $this->line('3. Go to "Webhooks" in the left sidebar');
        $this->line('4. Click "Add Callback URL"');
        $this->line('5. Enter your webhook URL: ' . $webhookUrl);
        $this->line('6. Click "Verify and Save"');
        $this->line('7. Subscribe to these fields:');
        $this->line('   - messaging (for DMs, story replies, story mentions)');
        $this->line('   - comments (for comments)');
        $this->line('   - mentions (for mentions)');
        $this->newLine();
        $this->warn('⚠️ For local development, your webhook URL must be publicly accessible!');
        $this->info('   Use Expose, ngrok, or deploy to a server.');

        return 0;
    }
}






