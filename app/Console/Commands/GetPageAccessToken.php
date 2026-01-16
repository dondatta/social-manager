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

        // First, verify the token is valid and check permissions
        $this->info('Verifying User Access Token...');
        $verifyResponse = Http::withToken($userToken)
            ->get('https://graph.facebook.com/v21.0/me', [
                'fields' => 'id,name',
            ]);

        if (!$verifyResponse->successful()) {
            $error = $verifyResponse->json();
            $this->error('User Access Token is invalid!');
            $this->error('Error: ' . ($error['error']['message'] ?? 'Unknown error'));
            $this->newLine();
            $this->warn('Make sure you:');
            $this->line('1. Copied the full token (no spaces, no truncation)');
            $this->line('2. Generated a User Token (not Page Token)');
            $this->line('3. Selected the correct permissions');
            return 1;
        }

        $userInfo = $verifyResponse->json();
        $this->info('✓ Token is valid for user: ' . ($userInfo['name'] ?? $userInfo['id']));
        
        // Check token permissions
        $this->newLine();
        $this->info('Checking token permissions...');
        $debugResponse = Http::withToken($userToken)
            ->get('https://graph.facebook.com/v21.0/me/permissions');
        
        if ($debugResponse->successful()) {
            $permissions = $debugResponse->json('data', []);
            $grantedPermissions = array_filter($permissions, fn($p) => $p['status'] === 'granted');
            $permissionNames = array_column($grantedPermissions, 'permission');
            
            $this->line('Granted permissions: ' . implode(', ', $permissionNames));
            
            if (!in_array('pages_show_list', $permissionNames)) {
                $this->newLine();
                $this->error('⚠️ Missing required permission: pages_show_list');
                $this->warn('You need to regenerate your User Access Token with pages_show_list permission.');
                return 1;
            }
        }

        // Get pages
        $this->newLine();
        $this->info('Fetching your pages...');
        $response = Http::withToken($userToken)
            ->get('https://graph.facebook.com/v21.0/me/accounts', [
                'fields' => 'id,name,access_token,instagram_business_account',
                'limit' => 100,
            ]);

        if (!$response->successful()) {
            $error = $response->json();
            $errorCode = $error['error']['code'] ?? null;
            $errorMessage = $error['error']['message'] ?? 'Unknown error';
            
            // Check if they're using a Page Access Token instead of User Access Token
            if ($errorCode == 100 && strpos($errorMessage, 'accounts') !== false) {
                $this->error('⚠️ You\'re using a Page Access Token, but this command needs a User Access Token!');
                $this->newLine();
                $this->info('It looks like you already have a Page Access Token. If that\'s the case, you can use it directly!');
                $this->newLine();
                $this->warn('If you already have a Page Access Token:');
                $this->line('1. You don\'t need to run this command');
                $this->line('2. Just add it directly to your .env file:');
                $this->line('   IG_ACCESS_TOKEN="your_page_access_token"');
                $this->line('   IG_PAGE_ID="your_page_id"');
                $this->newLine();
                $this->info('To get your Page ID:');
                $this->line('1. Use the Page Access Token in Graph API Explorer');
                $this->line('2. Make a GET request to: /me');
                $this->line('3. The "id" field is your Page ID');
                $this->newLine();
                $this->warn('If you need to get a User Access Token instead:');
                $this->line('1. Go to: https://developers.facebook.com/tools/explorer/');
                $this->line('2. Select your app');
                $this->line('3. Select "User Token" (NOT Page Token)');
                $this->line('4. Click "Generate Access Token"');
                $this->line('5. Select permissions: pages_show_list, pages_messaging, instagram_basic');
                $this->line('6. Copy the User Access Token and run this command again');
                return 1;
            }
            
            $this->error('Failed to fetch pages:');
            $this->error('Status: ' . $response->status());
            $this->error('Error: ' . $errorMessage);
            if ($errorCode) {
                $this->error('Error Code: ' . $errorCode);
            }
            $this->newLine();
            $this->warn('Full API response:');
            $this->line(json_encode($error, JSON_PRETTY_PRINT));
            return 1;
        }

        $pages = $response->json('data', []);
        $responseData = $response->json();

        if (empty($pages)) {
            $this->warn('No pages found via /me/accounts. Trying alternative methods...');
            $this->newLine();
            
            // Try Business Manager approach
            $this->info('Attempting to find pages via Business Manager...');
            $businessResponse = Http::withToken($userToken)
                ->get('https://graph.facebook.com/v21.0/me/businesses', [
                    'fields' => 'id,name',
                ]);
            
            if ($businessResponse->successful()) {
                $businesses = $businessResponse->json('data', []);
                if (!empty($businesses)) {
                    $this->info('Found ' . count($businesses) . ' business(es). Trying to get pages from Business Manager...');
                    // Try to get pages from first business
                    $businessId = $businesses[0]['id'];
                    $businessPagesResponse = Http::withToken($userToken)
                        ->get("https://graph.facebook.com/v21.0/{$businessId}/owned_pages", [
                            'fields' => 'id,name,access_token,instagram_business_account',
                        ]);
                    
                    if ($businessPagesResponse->successful()) {
                        $businessPages = $businessPagesResponse->json('data', []);
                        if (!empty($businessPages)) {
                            $this->info('✓ Found ' . count($businessPages) . ' page(s) via Business Manager!');
                            $pages = $businessPages;
                        }
                    }
                }
            }
            
            // If still no pages, try to get Instagram Business Account and find its connected page
            if (empty($pages)) {
                $this->info('Trying to find Instagram Business Account directly...');
                
                // Ask for Instagram Business Account ID
                $this->newLine();
                $this->info('You can find your Instagram Business Account ID in:');
                $this->line('1. Facebook App Dashboard → Instagram Product → Basic Display');
                $this->line('2. Or go to: https://developers.facebook.com/apps/{your-app-id}/instagram/basic-display/');
                $this->newLine();
                
                if ($this->confirm('Do you know your Instagram Business Account ID?', false)) {
                    $igAccountId = $this->ask('Enter your Instagram Business Account ID');
                    
                    if ($igAccountId) {
                        // Try to get the connected page from Instagram account
                        $igResponse = Http::withToken($userToken)
                            ->get("https://graph.facebook.com/v21.0/{$igAccountId}", [
                                'fields' => 'connected_facebook_page',
                            ]);
                        
                        if ($igResponse->successful()) {
                            $igData = $igResponse->json();
                            if (isset($igData['connected_facebook_page']['id'])) {
                                $pageId = $igData['connected_facebook_page']['id'];
                                $this->info("✓ Found connected Facebook Page: {$pageId}");
                                
                                // Now get the page access token
                                $pageResponse = Http::withToken($userToken)
                                    ->get("https://graph.facebook.com/v21.0/{$pageId}", [
                                        'fields' => 'id,name,access_token',
                                    ]);
                                
                                if ($pageResponse->successful()) {
                                    $pageData = $pageResponse->json();
                                    if (isset($pageData['access_token'])) {
                                        $this->info('✓ Successfully retrieved Page Access Token!');
                                        $this->newLine();
                                        $this->info('Your Page Access Token:');
                                        $this->line($pageData['access_token']);
                                        $this->newLine();
                                        $this->warn('⚠️ Add this to your .env file:');
                                        $this->line('IG_ACCESS_TOKEN="' . $pageData['access_token'] . '"');
                                        $this->line('IG_PAGE_ID="' . $pageId . '"');
                                        $this->newLine();
                                        $this->info('Then test it with: php artisan instagram:test-token');
                                        return 0;
                                    }
                                }
                            }
                        }
                    }
                }
                
                $this->warn('Note: You may need to use Graph API Explorer to get Page Access Token manually.');
                $this->newLine();
            }
        }
        
        if (empty($pages)) {
            $this->error('No pages found via any method. This could mean:');
            $this->newLine();
            $this->line('1. Your Instagram account is managed through Business Manager');
            $this->line('2. You don\'t have direct admin access to the Facebook Page');
            $this->line('3. Your pages require different permissions');
            $this->newLine();
            $this->warn('Alternative Solution: Use Graph API Explorer Directly');
            $this->newLine();
            $this->info('Since the API isn\'t finding pages, you can get the Page Access Token manually:');
            $this->newLine();
            $this->line('1. Go to: https://developers.facebook.com/tools/explorer/');
            $this->line('2. Select your app');
            $this->line('3. Click the dropdown next to "User or Page"');
            $this->line('4. Select your Facebook PAGE (not User)');
            $this->line('5. Click "Generate Access Token"');
            $this->line('6. Select permissions: pages_messaging, instagram_basic');
            $this->line('7. Copy the generated Page Access Token');
            $this->newLine();
            $this->info('To find your Page ID:');
            $this->line('1. Go to your Facebook Page');
            $this->line('2. Click "About"');
            $this->line('3. Scroll down to find "Page ID"');
            $this->line('4. Or use: https://www.facebook.com/your-page-name');
            $this->line('   The Page ID is in the URL or page source');
            $this->newLine();
            $this->warn('Then add to your .env file:');
            $this->line('IG_ACCESS_TOKEN="your_page_access_token_here"');
            $this->line('IG_PAGE_ID="your_page_id_here"');
            $this->newLine();
            $this->info('API Response (for debugging):');
            $this->line(json_encode($responseData, JSON_PRETTY_PRINT));
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

