<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\InstagramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchUserProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $messageId
    ) {}

    public function handle(InstagramService $instagramService): void
    {
        $message = Message::find($this->messageId);
        
        if (!$message) {
            Log::warning('Message not found for profile fetch', ['message_id' => $this->messageId]);
            return;
        }

        // Skip if already has username and profile picture
        if ($message->instagram_username && $message->profile_picture_url) {
            return;
        }

        try {
            $profile = $instagramService->getUserProfile($message->instagram_user_id);
            
            if ($profile) {
                $updateData = [];
                
                if (!$message->instagram_username && isset($profile['username'])) {
                    $updateData['instagram_username'] = $profile['username'];
                }
                
                // Try both field names for profile picture
                $profilePic = $profile['profile_picture_url'] ?? $profile['profile_pic'] ?? null;
                if (!$message->profile_picture_url && $profilePic) {
                    $updateData['profile_picture_url'] = $profilePic;
                }
                
                if (!empty($updateData)) {
                    $message->update($updateData);
                    Log::info('Updated message profile', [
                        'message_id' => $this->messageId,
                        'updates' => array_keys($updateData),
                    ]);
                }
            } else {
                Log::warning('Failed to fetch profile for message', [
                    'message_id' => $this->messageId,
                    'user_id' => $message->instagram_user_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error fetching user profile', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

