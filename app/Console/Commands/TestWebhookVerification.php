<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestWebhookVerification extends Command
{
    protected $signature = 'instagram:test-webhook-verification {--url= : The webhook URL to test}';
    protected $description = 'Test Instagram webhook verification endpoint';

    public function handle()
    {
        $this->info('Testing Instagram Webhook Verification...');
        $this->newLine();

        $webhookUrl = $this->option('url') ?: url('/webhooks/instagram');
        $verifyToken = config('services.instagram.verify_token', 'test_token');

        $this->info('Webhook URL: ' . $webhookUrl);
        $this->info('Verify Token: ' . $verifyToken);
        $this->newLine();

        // Simulate Facebook's verification request
        $challenge = 'test_challenge_12345';
        $testUrl = $webhookUrl . '?' . http_build_query([
            'hub.mode' => 'subscribe',
            'hub.challenge' => $challenge,
            'hub.verify_token' => $verifyToken,
        ]);

        $this->info('Sending verification request...');
        $this->line('URL: ' . $testUrl);
        $this->newLine();

        try {
            $response = Http::get($testUrl);

            if ($response->successful()) {
                $body = $response->body();
                
                if ($body === $challenge) {
                    $this->info('✓ Webhook verification is working correctly!');
                    $this->info('Response: ' . $body);
                    $this->newLine();
                    $this->info('Your webhook endpoint is ready for Facebook verification.');
                } else {
                    $this->error('✗ Verification failed!');
                    $this->warn('Expected: ' . $challenge);
                    $this->warn('Got: ' . $body);
                    $this->newLine();
                    $this->error('The endpoint should return the challenge value exactly.');
                }
            } else {
                $this->error('✗ Request failed!');
                $this->error('Status: ' . $response->status());
                $this->error('Response: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('✗ Error: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Make sure:');
            $this->line('1. Your local server is running');
            $this->line('2. The webhook URL is accessible');
            $this->line('3. If using ngrok, the tunnel is active');
        }

        $this->newLine();
        $this->info('To test with ngrok URL:');
        $this->line('php artisan instagram:test-webhook-verification --url="https://your-ngrok-url.ngrok-free.app/webhooks/instagram"');

        return 0;
    }
}






