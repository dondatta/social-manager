<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GetPageAccessToken extends Command
{
    protected $signature = 'instagram:get-page-token {--user-token= : Your User Access Token (optional, will prompt if not provided)}';
    protected $description = 'Get a Page Access Token for Instagram messaging without deprecated permissions';

    public function handle()
    {
        $this->info('Getting Page Access Token for Instagram Messaging...');
        $this->newLine();

        // Get user token
        $userToken = $this->option('user-token');
        if (!$userToken) {
            $this->warn('To get a Page Access Token, you need a User Access Token first.');
            $this->info('Steps:');
            $this->line('1. Go to: https://developers.facebook.com/tools/explorer/');
            $this->line('2. Select your app');
            $this->line('3. Select "User Token" (NOT Page Token)');
            $this->line('4. Click "Generate Access Token"');
            $this->line('5. Select ONLY these permissions:');
            $this->line('   - pages_show_list');
            $this->line('   - pages_messaging');
            $this->line('   - instagram_basic');
            $this->line('   - business_management (if using Business account)');
            $this->newLine();
            $this->warn('⚠️ DO NOT select manage_pages - it will cause errors!');
            $this->newLine();
            
            $userToken = $this->ask('Paste your User Access Token here');
        }

        if (!$userToken) {
            $this->error('User Access Token is required.');
            return 1;
        }

        // Get pages
        $this->info('Fetching your pages...');
        $response = Http::withToken($userToken)
            ->get('https://graph.facebook.com/v21.0/me/accounts', [
                'fields' => 'id,name,access_token,instagram_business_account',
            ]);

        if (!$response->successful()) {
            $error = $response->json();
            $this->error('Failed to fetch pages:');
            $this->error($error['error']['message'] ?? 'Unknown error');
            return 1;
        }

        $pages = $response->json('data', []);

        if (empty($pages)) {
            $this->error('No pages found. Make sure:');
            $this->line('1. You have admin access to a Facebook Page');
            $this->line('2. Your Instagram account is connected to that Page');
            $this->line('3. You selected pages_show_list permission');
            return 1;
        }

        // Display pages
        $this->newLine();
        $this->info('Found ' . count($pages) . ' page(s):');
        $this->newLine();

        $pageOptions = [];
        foreach ($pages as $index => $page) {
            $hasInstagram = isset($page['instagram_business_account']);
            $pageOptions[] = [
                'index' => $index + 1,
                'name' => $page['name'],
                'id' => $page['id'],
                'has_instagram' => $hasInstagram ? '✓ Yes' : '✗ No',
            ];
        }

        $this->table(
            ['#', 'Page Name', 'Page ID', 'Has Instagram'],
            array_map(function($page) {
                return [
                    $page['index'],
                    $page['name'],
                    $page['id'],
                    $page['has_instagram'],
                ];
            }, $pageOptions)
        );

        // Select page
        $selectedIndex = $this->ask('Enter the number of the page you want to use', 1);
        $selectedPage = $pages[$selectedIndex - 1] ?? null;

        if (!$selectedPage) {
            $this->error('Invalid selection.');
            return 1;
        }

        if (!isset($selectedPage['instagram_business_account'])) {
            $this->warn('⚠️ This page does not have an Instagram Business account connected.');
            if (!$this->confirm('Continue anyway?', false)) {
                return 0;
            }
        }

        // Get the page access token
        $pageAccessToken = $selectedPage['access_token'] ?? null;

        if (!$pageAccessToken) {
            $this->error('Page Access Token not found in response.');
            return 1;
        }

        // Verify the token
        $this->newLine();
        $this->info('Verifying Page Access Token...');
        
        $verifyResponse = Http::withToken($pageAccessToken)
            ->get('https://graph.facebook.com/v21.0/me', [
                'fields' => 'id,name',
            ]);

        if ($verifyResponse->successful()) {
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
        } else {
            $this->warn('⚠️ Could not verify token, but it was generated.');
        }

        // Display the token
        $this->newLine();
        $this->info('Your Page Access Token:');
        $this->line($pageAccessToken);
        $this->newLine();
        $this->warn('⚠️ Add this to your .env file:');
        $this->line('IG_ACCESS_TOKEN="' . $pageAccessToken . '"');
        $this->line('IG_PAGE_ID="' . $selectedPage['id'] . '"');
        $this->newLine();
        $this->info('Then test it with: php artisan instagram:test-token');

        return 0;
    }
}

