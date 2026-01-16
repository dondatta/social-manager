<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramService
{
    protected string $baseUrl = 'https://graph.facebook.com/v21.0';
    protected ?string $accessToken;
    protected ?string $pageId;

    public function __construct()
    {
        // Check database first, then fall back to config/env
        $this->accessToken = $this->getAccessToken();
        $this->pageId = $this->getPageId();
    }

    protected function getAccessToken(): ?string
    {
        // Check database first
        try {
            $setting = \App\Models\AutomationSetting::where('key', 'instagram_access_token')->first();
            if ($setting && !empty($setting->value)) {
                return $setting->value;
            }
        } catch (\Exception $e) {
            // Database not available, fall through to config
        }
        
        // Fall back to config/env
        return config('services.instagram.access_token');
    }

    protected function getPageId(): ?string
    {
        // Check database first
        try {
            $setting = \App\Models\AutomationSetting::where('key', 'instagram_page_id')->first();
            if ($setting && !empty($setting->value)) {
                return $setting->value;
            }
        } catch (\Exception $e) {
            // Database not available, fall through to config
        }
        
        // Fall back to config/env
        return config('services.instagram.page_id');
    }

    /**
     * Send a text message to a user.
     * Supports standard DMs and Private Replies to Comments.
     * 
     * @param string $recipientId The User ID or Comment ID
     * @param string $message The text to send
     * @param bool $isCommentReply Set true if $recipientId is a Comment ID
     */
    /**
     * Send a text message to a user.
     * Supports standard DMs and Private Replies to Comments.
     * 
     * @param string $recipientId The User ID or Comment ID
     * @param string $message The text to send
     * @param bool $isCommentReply Set true if $recipientId is a Comment ID
     * @return array Returns ['success' => bool, 'error' => string|null]
     */
    public function sendDm(string $recipientId, string $message, bool $isCommentReply = false): array
    {
        if (!$this->accessToken) {
            Log::error('Instagram Access Token is missing.');
            return ['success' => false, 'error' => 'Instagram Access Token is missing. Please check your .env file.'];
        }

        $url = "{$this->baseUrl}/me/messages";

        $recipient = $isCommentReply 
            ? ['comment_id' => $recipientId] 
            : ['id' => $recipientId];

        $response = Http::withToken($this->accessToken)
            ->post($url, [
                'recipient' => $recipient,
                'message' => ['text' => $message],
            ]);

        if ($response->successful()) {
            return ['success' => true, 'error' => null];
        }

        $errorData = $response->json();
        $errorMessage = $errorData['error']['message'] ?? 'Unknown error';
        $errorCode = $errorData['error']['code'] ?? null;
        
        // Provide user-friendly error messages
        $userFriendlyError = match($errorCode) {
            190 => 'Invalid or expired access token. Please update your IG_ACCESS_TOKEN in the .env file.',
            100 => 'Invalid user ID or user has not engaged with you in the last 24 hours.',
            10 => 'Permission denied. Check your Instagram API permissions.',
            default => $errorMessage . (isset($errorCode) ? " (Error Code: {$errorCode})" : ''),
        };

        Log::error('Failed to send Instagram DM', [
            'recipient_id' => $recipientId,
            'is_comment_reply' => $isCommentReply,
            'response' => $errorData,
            'status' => $response->status(),
            'error_code' => $errorCode,
        ]);

        return ['success' => false, 'error' => $userFriendlyError];
    }

    /**
     * Get user profile information (e.g. name, username, profile picture)
     * 
     * Based on Instagram Graph API v14.0+ User Profile API documentation.
     * Note: For Instagram-scoped IDs from messaging, profile data may not be available
     * due to API limitations. This method tries multiple approaches.
     */
    public function getUserProfile(string $userId): ?array
    {
        if (!$this->accessToken) {
            Log::error('Instagram Access Token is missing for getUserProfile');
            return null;
        }

        // Approach 1: Try User Profile API with username field (v14.0+)
        // According to docs: GET /v14.0/{user-id}?fields=username,name
        $url = "{$this->baseUrl}/{$userId}";

        // Try with username field first (as per v14.0+ documentation)
        $response = Http::withToken($this->accessToken)
            ->get($url, [
                'fields' => 'username,name,first_name,last_name,profile_pic,profile_picture_url',
            ]);

        if ($response->successful()) {
            $data = $response->json();
            
            // Normalize profile picture field name
            if (isset($data['profile_picture_url']) && !isset($data['profile_pic'])) {
                $data['profile_pic'] = $data['profile_picture_url'];
            } elseif (isset($data['profile_pic']) && !isset($data['profile_picture_url'])) {
                $data['profile_picture_url'] = $data['profile_pic'];
            }
            
            Log::info('Successfully fetched user profile', [
                'user_id' => $userId,
                'has_username' => isset($data['username']),
                'has_profile_pic' => isset($data['profile_pic']) || isset($data['profile_picture_url']),
            ]);
            
            return $data;
        }

        // Approach 2: Try with metadata=1 to see what fields are available
        $metadataResponse = Http::withToken($this->accessToken)
            ->get($url, [
                'metadata' => 1,
            ]);
        
        if ($metadataResponse->successful()) {
            $metadata = $metadataResponse->json();
            Log::info('Retrieved metadata for user', [
                'user_id' => $userId,
                'available_fields' => isset($metadata['metadata']['fields']) 
                    ? array_column($metadata['metadata']['fields'], 'name') 
                    : null,
            ]);
        }

        // Approach 3: Try conversations endpoint if we have page ID
        if ($this->pageId) {
            try {
                // Get conversations and check if user info is in participants
                $conversationsUrl = "{$this->baseUrl}/{$this->pageId}/conversations";
                $conversationsResponse = Http::withToken($this->accessToken)
                    ->get($conversationsUrl, [
                        'user_id' => $userId,
                        'fields' => 'participants{id,name,username}',
                    ]);
                
                if ($conversationsResponse->successful()) {
                    $conversations = $conversationsResponse->json();
                    Log::info('Found conversation for user', [
                        'user_id' => $userId,
                        'conversations' => $conversations,
                    ]);
                    // Extract user info from participants if available
                    if (isset($conversations['data'][0]['participants']['data'])) {
                        foreach ($conversations['data'][0]['participants']['data'] as $participant) {
                            if ($participant['id'] === $userId) {
                                return [
                                    'id' => $participant['id'],
                                    'username' => $participant['username'] ?? null,
                                    'name' => $participant['name'] ?? null,
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::debug('Conversations API call failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Log detailed error information
        $errorResponse = $response->json();
        $errorCode = $errorResponse['error']['code'] ?? null;
        $errorMessage = $errorResponse['error']['message'] ?? null;

        Log::warning('Failed to fetch Instagram user profile', [
            'user_id' => $userId,
            'url' => $url,
            'status' => $response->status(),
            'error_type' => $errorResponse['error']['type'] ?? null,
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
            'note' => 'Instagram-scoped IDs from messaging may not support profile data retrieval',
        ]);

        return null;
    }
}
