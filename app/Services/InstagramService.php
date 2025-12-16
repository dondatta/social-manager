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
        $this->accessToken = config('services.instagram.access_token');
        $this->pageId = config('services.instagram.page_id');
    }

    /**
     * Send a text message to a user.
     * Supports standard DMs and Private Replies to Comments.
     * 
     * @param string $recipientId The User ID or Comment ID
     * @param string $message The text to send
     * @param bool $isCommentReply Set true if $recipientId is a Comment ID
     */
    public function sendDm(string $recipientId, string $message, bool $isCommentReply = false): bool
    {
        if (!$this->accessToken) {
            Log::error('Instagram Access Token is missing.');
            return false;
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
            return true;
        }

        Log::error('Failed to send Instagram DM', [
            'recipient_id' => $recipientId,
            'is_comment_reply' => $isCommentReply,
            'response' => $response->json(),
            'status' => $response->status(),
        ]);

        return false;
    }

    /**
     * Get user profile information (e.g. name)
     * 
     * Note: Instagram Graph API has limitations on fetching user profiles.
     * For Instagram scoped IDs from messaging, we may need to use the conversations API
     * or the user might need to have interacted with your page.
     */
    public function getUserProfile(string $userId): ?array
    {
        if (!$this->accessToken) {
            Log::error('Instagram Access Token is missing for getUserProfile');
            return null;
        }

        // Try multiple endpoints and approaches
        
        // Approach 1: Direct user ID lookup (may not work for Instagram scoped IDs)
        $url = "{$this->baseUrl}/{$userId}";
        
        $response = Http::withToken($this->accessToken)
            ->get($url, [
                'fields' => 'name,first_name,last_name,profile_pic,profile_picture_url,username',
            ]);

        if ($response->successful()) {
            $data = $response->json();
            
            // Normalize profile picture field name
            if (isset($data['profile_picture_url']) && !isset($data['profile_pic'])) {
                $data['profile_pic'] = $data['profile_picture_url'];
            } elseif (isset($data['profile_pic']) && !isset($data['profile_picture_url'])) {
                $data['profile_picture_url'] = $data['profile_pic'];
            }
            
            return $data;
        }

        // Approach 2: Try using the page's conversations endpoint
        // This might work better for Instagram messaging users
        if ($this->pageId) {
            $conversationsUrl = "{$this->baseUrl}/{$this->pageId}/conversations";
            $conversationsResponse = Http::withToken($this->accessToken)
                ->get($conversationsUrl, [
                    'user_id' => $userId,
                    'fields' => 'participants',
                ]);
            
            if ($conversationsResponse->successful()) {
                $conversations = $conversationsResponse->json();
                // If we find the conversation, we might be able to get user info from it
                Log::info('Found conversation for user', [
                    'user_id' => $userId,
                    'conversations' => $conversations,
                ]);
            }
        }

        // Log detailed error information
        $errorResponse = $response->json();
        Log::warning('Failed to fetch Instagram user profile', [
            'user_id' => $userId,
            'url' => $url,
            'status' => $response->status(),
            'response' => $errorResponse,
            'error_type' => $errorResponse['error']['type'] ?? null,
            'error_message' => $errorResponse['error']['message'] ?? null,
            'error_code' => $errorResponse['error']['code'] ?? null,
        ]);

        return null;
    }
}
