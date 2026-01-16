<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckTokenExpiration extends Command
{
    protected $signature = 'instagram:check-token-expiration';
    protected $description = 'Check Instagram access token expiration and convert to long-lived if needed';

    public function handle()
    {
        $this->info('Checking Instagram Access Token Expiration...');
        $this->newLine();

        $accessToken = config('services.instagram.access_token');
        $appId = config('services.instagram.app_id'); // You may need to add this to config
        $appSecret = config('services.instagram.app_secret');

        if (!$accessToken) {
            $this->error('IG_ACCESS_TOKEN is not set in your .env file!');
            return 1;
        }

        // First, verify the token is valid
        $this->info('1. Verifying token validity...');
        $verifyResponse = Http::withToken($accessToken)
            ->get('https://graph.facebook.com/v21.0/me', [
                'fields' => 'id,name',
            ]);

        if (!$verifyResponse->successful()) {
            $error = $verifyResponse->json();
            $this->error('✗ Token is invalid!');
            $this->error('Error: ' . ($error['error']['message'] ?? 'Unknown error'));
            $this->newLine();
            $this->warn('The token in your .env file may be:');
            $this->line('1. Expired');
            $this->line('2. Missing quotes (should be: IG_ACCESS_TOKEN="token")');
            $this->line('3. Truncated or incomplete');
            return 1;
        }

        $this->info('✓ Token is valid!');
        $pageInfo = $verifyResponse->json();
        $this->line('Page: ' . ($pageInfo['name'] ?? $pageInfo['id']));

        // Check token expiration
        $this->newLine();
        $this->info('2. Checking token expiration...');
        
        // Use debug_token endpoint to get expiration info
        $debugResponse = Http::get('https://graph.facebook.com/v21.0/debug_token', [
            'input_token' => $accessToken,
            'access_token' => $accessToken,
        ]);

        if ($debugResponse->successful()) {
            $debugData = $debugResponse->json('data', []);
            
            $expiresAt = $debugData['expires_at'] ?? null;
            $isValid = $debugData['is_valid'] ?? false;
            $type = $debugData['type'] ?? 'unknown';
            
            $this->table(
                ['Property', 'Value'],
                [
                    ['Token Type', $type],
                    ['Is Valid', $isValid ? '✓ Yes' : '✗ No'],
                    ['Expires At', $expiresAt ? date('Y-m-d H:i:s', $expiresAt) : 'Never'],
                    ['Days Until Expiry', $expiresAt ? round(($expiresAt - time()) / 86400) . ' days' : 'N/A'],
                ]
            );

            if ($expiresAt) {
                $daysUntilExpiry = ($expiresAt - time()) / 86400;
                
                if ($daysUntilExpiry > 50) {
                    $this->info('✓ Token is long-lived and has plenty of time remaining!');
                } elseif ($daysUntilExpiry > 0) {
                    $this->warn('⚠️ Token expires in ' . round($daysUntilExpiry) . ' days');
                    $this->info('Consider refreshing it soon.');
                } else {
                    $this->error('✗ Token has expired!');
                    $this->info('You need to generate a new token.');
                }
            } else {
                $this->info('Token does not expire (unlikely for Instagram tokens)');
            }
        } else {
            $this->warn('Could not check token expiration details');
        }

        // Instructions for extending token
        $this->newLine();
        $this->info('3. To extend token to long-lived (60 days):');
        $this->line('If your token is short-lived, you can extend it:');
        $this->newLine();
        $this->line('Option 1: Use Access Token Tool');
        $this->line('1. Go to: https://developers.facebook.com/tools/accesstoken/');
        $this->line('2. Find your token in the list');
        $this->line('3. Click "Extend Access Token"');
        $this->newLine();
        $this->line('Option 2: Use API endpoint');
        if ($appId && $appSecret) {
            $extendUrl = "https://graph.facebook.com/v21.0/oauth/access_token?" . http_build_query([
                'grant_type' => 'fb_exchange_token',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'fb_exchange_token' => $accessToken,
            ]);
            $this->line('GET ' . $extendUrl);
        } else {
            $this->line('GET https://graph.facebook.com/v21.0/oauth/access_token?');
            $this->line('  grant_type=fb_exchange_token&');
            $this->line('  client_id={your-app-id}&');
            $this->line('  client_secret={your-app-secret}&');
            $this->line('  fb_exchange_token={current-token}');
        }

        return 0;
    }
}


