<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UsePageAccessToken extends Command
{
    protected $signature = 'instagram:use-page-token {--token= : Your Page Access Token (optional, will prompt if not provided)}';
    protected $description = 'Validate and use an existing Page Access Token for Instagram messaging';

    public function handle()
    {
        $this->info('Validating Page Access Token for Instagram Messaging...');
        $this->newLine();

        // Get page token
        $pageToken = $this->option('token');
        if (!$pageToken) {
            $this->info('If you already have a Page Access Token from Graph API Explorer, you can use it directly.');
            $this->newLine();
            $this->info('To get a Page Access Token:');
            $this->line('1. Go to: https://developers.facebook.com/tools/explorer/');
            $this->line('2. Select your app');
            $this->line('3. Select your Facebook PAGE (not User) from the dropdown');
            $this->line('4. Click "Generate Access Token"');
            $this->line('5. Select permissions: pages_messaging, instagram_basic');
            $this->line('6. Copy the generated token');
            $this->newLine();
            
            $pageToken = $this->ask('Paste your Page Access Token here');
        }

        if (!$pageToken) {
            $this->error('Page Access Token is required.');
            return 1;
        }

        // Verify the token
        $this->info('Verifying Page Access Token...');
        $verifyResponse = Http::withToken($pageToken)
            ->get('https://graph.facebook.com/v21.0/me', [
                'fields' => 'id,name',
            ]);

        if (!$verifyResponse->successful()) {
            $error = $verifyResponse->json();
            $this->error('Page Access Token is invalid!');
            $this->error('Error: ' . ($error['error']['message'] ?? 'Unknown error'));
            $this->newLine();
            $this->warn('Make sure you:');
            $this->line('1. Copied the full token (no spaces, no truncation)');
            $this->line('2. Generated a Page Token (not User Token)');
            $this->line('3. Selected the correct permissions');
            return 1;
        }

        $pageInfo = $verifyResponse->json();
        $this->info('✓ Token is valid!');
        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['Page ID', $pageInfo['id']],
                ['Page Name', $pageInfo['name']],
            ]
        );

        // Check if Instagram is connected
        $this->newLine();
        $this->info('Checking Instagram connection...');
        $igResponse = Http::withToken($pageToken)
            ->get('https://graph.facebook.com/v21.0/me', [
                'fields' => 'instagram_business_account',
            ]);

        if ($igResponse->successful()) {
            $igData = $igResponse->json();
            if (isset($igData['instagram_business_account']['id'])) {
                $this->info('✓ Instagram Business Account is connected!');
                $this->line('Instagram Account ID: ' . $igData['instagram_business_account']['id']);
            } else {
                $this->warn('⚠️ No Instagram Business Account found for this page.');
                $this->line('Make sure your Instagram account is connected to this Facebook Page.');
            }
        }

        // Display the token
        $this->newLine();
        $this->info('Your Page Access Token:');
        $this->line($pageToken);
        $this->newLine();
        $this->warn('⚠️ Add this to your .env file:');
        $this->line('IG_ACCESS_TOKEN="' . $pageToken . '"');
        $this->line('IG_PAGE_ID="' . $pageInfo['id'] . '"');
        $this->newLine();
        $this->info('Then test it with: php artisan instagram:test-token');

        return 0;
    }
}


