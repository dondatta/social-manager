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
     */
    public function getUserProfile(string $userId): ?array
    {
        if (!$this->accessToken) {
            return null;
        }

        // Note: Getting user profile usually requires specific permissions or that the user has interacted.
        // For Instagram scoped IDs, fields might be limited.
        $url = "{$this->baseUrl}/{$userId}";

        $response = Http::withToken($this->accessToken)
            ->get($url, [
                'fields' => 'name,first_name,last_name,profile_pic,username',
            ]);

        if ($response->successful()) {
            return $response->json();
        }

        Log::warning('Failed to fetch Instagram user profile', [
            'user_id' => $userId,
            'response' => $response->json(),
        ]);

        return null;
    }
}
