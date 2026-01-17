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

        // Approach 1: Try graph.instagram.com endpoint (ManyChat-style approach)
        // GET https://graph.instagram.com/{sender-id}?fields=username,name,profile_pic&access_token={token}
        try {
            $instagramUrl = "https://graph.instagram.com/{$userId}";
            
            // Try with access_token as query parameter (as suggested)
            $response = Http::get($instagramUrl, [
                'fields' => 'username,name,profile_pic',
                'access_token' => $this->accessToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Log full response for debugging
                Log::info('graph.instagram.com response (with access_token param)', [
                    'user_id' => $userId,
                    'response' => $data,
                ]);
                
                // Normalize profile picture field name
                if (isset($data['profile_pic'])) {
                    $data['profile_picture_url'] = $data['profile_pic'];
                }
                
                if (!empty($data['username']) || !empty($data['profile_pic'])) {
                    Log::info('Successfully fetched user profile via graph.instagram.com', [
                        'user_id' => $userId,
                        'has_username' => isset($data['username']),
                        'has_profile_pic' => isset($data['profile_pic']),
                    ]);
                    return $data;
                }
            } else {
                $errorData = $response->json();
                Log::warning('graph.instagram.com endpoint failed (with access_token param)', [
                    'user_id' => $userId,
                    'status' => $response->status(),
                    'error' => $errorData,
                    'error_message' => $errorData['error']['message'] ?? null,
                    'error_code' => $errorData['error']['code'] ?? null,
                ]);
                
                // Try with token in header as fallback
                $response2 = Http::withToken($this->accessToken)
                    ->get($instagramUrl, [
                        'fields' => 'username,name,profile_pic',
                    ]);
                
                if ($response2->successful()) {
                    $data = $response2->json();
                    
                    Log::info('graph.instagram.com response (with token header)', [
                        'user_id' => $userId,
                        'response' => $data,
                    ]);
                    
                    if (isset($data['profile_pic'])) {
                        $data['profile_picture_url'] = $data['profile_pic'];
                    }
                    
                    if (!empty($data['username']) || !empty($data['profile_pic'])) {
                        Log::info('Successfully fetched user profile via graph.instagram.com (header)', [
                            'user_id' => $userId,
                            'has_username' => isset($data['username']),
                            'has_profile_pic' => isset($data['profile_pic']),
                        ]);
                        return $data;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('graph.instagram.com call exception', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Approach 2: Try Conversations API to get participant info (ManyChat uses this)
        // This is often more reliable for messaging-scoped IDs
        if ($this->pageId) {
            try {
                // Get all conversations and search for this user
                $conversationsUrl = "{$this->baseUrl}/{$this->pageId}/conversations";
                $conversationsResponse = Http::withToken($this->accessToken)
                    ->get($conversationsUrl, [
                        'fields' => 'participants{id,name,username,profile_pic}',
                        'limit' => 100, // Get more conversations
                    ]);
                
                if ($conversationsResponse->successful()) {
                    $conversations = $conversationsResponse->json();
                    
                    // Search through conversations for this user
                    if (isset($conversations['data'])) {
                        foreach ($conversations['data'] as $conversation) {
                            if (isset($conversation['participants']['data'])) {
                                foreach ($conversation['participants']['data'] as $participant) {
                                    if ($participant['id'] === $userId) {
                                        $profile = [
                                            'id' => $participant['id'],
                                            'username' => $participant['username'] ?? null,
                                            'name' => $participant['name'] ?? null,
                                        ];
                                        
                                        // Handle profile picture
                                        if (isset($participant['profile_pic'])) {
                                            $profile['profile_pic'] = $participant['profile_pic'];
                                            $profile['profile_picture_url'] = $participant['profile_pic'];
                                        }
                                        
                                        Log::info('Found user profile via conversations API', [
                                            'user_id' => $userId,
                                            'has_username' => !empty($profile['username']),
                                            'has_profile_pic' => !empty($profile['profile_pic']),
                                        ]);
                                        
                                        return $profile;
                                    }
                                }
                            }
                        }
                    }
                } else {
                    Log::debug('Conversations API call failed', [
                        'user_id' => $userId,
                        'status' => $conversationsResponse->status(),
                        'error' => $conversationsResponse->json(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::debug('Conversations API call exception', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Approach 3: Try graph.facebook.com endpoint (fallback)
        // Also try with explicit access_token in query string (some endpoints prefer this)
        try {
            $url = "{$this->baseUrl}/{$userId}";
            
            // Try with token in header first
            $response = Http::withToken($this->accessToken)
                ->get($url, [
                    'fields' => 'id,username,name,first_name,last_name,profile_pic,profile_picture_url',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('graph.facebook.com response', [
                    'user_id' => $userId,
                    'response' => $data,
                ]);
                
                // Normalize profile picture field name
                if (isset($data['profile_picture_url']) && !isset($data['profile_pic'])) {
                    $data['profile_pic'] = $data['profile_picture_url'];
                } elseif (isset($data['profile_pic']) && !isset($data['profile_picture_url'])) {
                    $data['profile_picture_url'] = $data['profile_pic'];
                }
                
                if (!empty($data['username']) || !empty($data['profile_pic'])) {
                    Log::info('Successfully fetched user profile via graph.facebook.com', [
                        'user_id' => $userId,
                        'has_username' => isset($data['username']),
                        'has_profile_pic' => isset($data['profile_pic']) || isset($data['profile_picture_url']),
                    ]);
                    return $data;
                }
            } else {
                $errorData = $response->json();
                Log::warning('graph.facebook.com endpoint failed', [
                    'user_id' => $userId,
                    'status' => $response->status(),
                    'error' => $errorData,
                    'error_message' => $errorData['error']['message'] ?? null,
                    'error_code' => $errorData['error']['code'] ?? null,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('graph.facebook.com call exception', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        // Log detailed error information
        $errorResponse = $response->json() ?? [];
        $errorCode = $errorResponse['error']['code'] ?? null;
        $errorMessage = $errorResponse['error']['message'] ?? null;

        Log::warning('Failed to fetch Instagram user profile', [
            'user_id' => $userId,
            'status' => $response->status(),
            'error_type' => $errorResponse['error']['type'] ?? null,
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
            'note' => 'Tried graph.instagram.com, conversations API, and graph.facebook.com - all failed',
        ]);

        return null;
    }
}
