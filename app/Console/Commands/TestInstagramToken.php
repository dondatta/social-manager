<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestInstagramToken extends Command
{
    protected $signature = 'instagram:test-token';
    protected $description = 'Test Instagram access token configuration and validity';

    public function handle()
    {
        $this->info('Testing Instagram Access Token Configuration...');
        $this->newLine();

        // Check config
        $accessToken = config('services.instagram.access_token');
        $pageId = config('services.instagram.page_id');
        $appSecret = config('services.instagram.app_secret');

        $this->info('Configuration Check:');
        $this->table(
            ['Setting', 'Status', 'Value'],
            [
                ['IG_ACCESS_TOKEN', $accessToken ? '✓ Set' : '✗ Missing', $accessToken ? substr($accessToken, 0, 20) . '...' : 'Not found'],
                ['IG_PAGE_ID', $pageId ? '✓ Set' : '✗ Missing', $pageId ?: 'Not found'],
                ['IG_APP_SECRET', $appSecret ? '✓ Set' : '✗ Missing', $appSecret ? substr($appSecret, 0, 10) . '...' : 'Not found'],
            ]
        );

        if (!$accessToken) {
            $this->error('IG_ACCESS_TOKEN is not set in your .env file!');
            $this->warn('Add this to your .env file:');
            $this->line('IG_ACCESS_TOKEN=your_access_token_here');
            return 1;
        }

        // Check token format
        $this->newLine();
        $this->info('Token Format Check:');
        
        if (strlen($accessToken) < 50) {
            $this->warn('Token seems too short. Instagram access tokens are typically 200+ characters.');
        }
        
        if (strpos($accessToken, ' ') !== false) {
            $this->error('Token contains spaces! Make sure there are no spaces in your .env value.');
            $this->warn('In .env, use: IG_ACCESS_TOKEN="your_token_here" (with quotes if it contains special characters)');
        }

        // Test token with a simple API call
        $this->newLine();
        $this->info('Testing Token with API Call...');
        
        // Try to get page info (simpler than user profile)
        if ($pageId) {
            $url = "https://graph.facebook.com/v21.0/{$pageId}";
            $response = Http::withToken($accessToken)
                ->get($url, [
                    'fields' => 'id,name',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->info('✓ Token is valid!');
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Page ID', $data['id'] ?? 'N/A'],
                        ['Page Name', $data['name'] ?? 'N/A'],
                    ]
                );
            } else {
                $error = $response->json();
                $this->error('✗ Token validation failed!');
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Status', $response->status()],
                        ['Error Type', $error['error']['type'] ?? 'N/A'],
                        ['Error Code', $error['error']['code'] ?? 'N/A'],
                        ['Error Message', $error['error']['message'] ?? 'N/A'],
                    ]
                );

                if (($error['error']['code'] ?? null) == 190) {
                    $this->newLine();
                    $this->warn('Error Code 190 means: Invalid OAuth access token');
                    $this->info('Possible causes:');
                    $this->line('1. Token has expired (Page Access Tokens expire after 60 days)');
                    $this->line('2. Token format is incorrect');
                    $this->line('3. Token was revoked or invalidated');
                    $this->line('4. Token is missing quotes in .env file');
                    $this->newLine();
                    $this->info('To fix:');
                    $this->line('1. Go to https://developers.facebook.com/tools/explorer/');
                    $this->line('2. Select your app and page');
                    $this->line('3. Generate a new Page Access Token');
                    $this->line('4. Update IG_ACCESS_TOKEN in your .env file');
                }
            }
        } else {
            // Try /me endpoint
            $url = "https://graph.facebook.com/v21.0/me";
            $response = Http::withToken($accessToken)
                ->get($url, [
                    'fields' => 'id,name',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->info('✓ Token is valid!');
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['ID', $data['id'] ?? 'N/A'],
                        ['Name', $data['name'] ?? 'N/A'],
                    ]
                );
            } else {
                $error = $response->json();
                $this->error('✗ Token validation failed!');
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Status', $response->status()],
                        ['Error Type', $error['error']['type'] ?? 'N/A'],
                        ['Error Code', $error['error']['code'] ?? 'N/A'],
                        ['Error Message', $error['error']['message'] ?? 'N/A'],
                    ]
                );
            }
        }

        // Check if token needs to be a Page Access Token
        $this->newLine();
        $this->info('Token Type Check:');
        $this->line('For Instagram messaging, you need a PAGE ACCESS TOKEN, not a User Access Token.');
        $this->line('Page Access Tokens:');
        $this->line('  - Start with the page ID (e.g., "123456789|...")');
        $this->line('  - Can be generated from: https://developers.facebook.com/tools/explorer/');
        $this->line('  - Select your Page (not User) in the dropdown');
        $this->line('  - Click "Generate Access Token"');

        return 0;
    }
}

