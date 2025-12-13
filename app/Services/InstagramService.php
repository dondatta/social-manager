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
     */
    public function sendDm(string $recipientId, string $message): bool
    {
        if (!$this->accessToken) {
            Log::error('Instagram Access Token is missing.');
            return false;
        }

        $url = "{$this->baseUrl}/me/messages";

        $response = Http::withToken($this->accessToken)
            ->post($url, [
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $message],
            ]);

        if ($response->successful()) {
            return true;
        }

        Log::error('Failed to send Instagram DM', [
            'recipient_id' => $recipientId,
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
                'fields' => 'name,first_name,last_name,profile_pic',
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
