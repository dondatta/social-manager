<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\HubspotService;
use App\Models\Message;

class SyncToHubspotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $username;
    protected $messageContent;
    protected $source;

    public function __construct($userId, $username, $messageContent, $source = 'Instagram DM')
    {
        $this->userId = $userId;
        $this->username = $username;
        $this->messageContent = $messageContent;
        $this->source = $source;
    }

    public function handle(HubspotService $hubspotService)
    {
        try {
            Log::info("Attempting to sync Instagram interaction to HubSpot for user: {$this->username}");
            
            // Find contact in HubSpot
            $contactId = $hubspotService->findContactByInstagramHandle($this->username);
            
            if (!$contactId) {
                Log::info("HubSpot: No contact found for Instagram username: {$this->username}. Skipping logging.");
                return; // Log only mode - don't create new contacts
            }

            // Log the message to the contact
            $success = $hubspotService->logMessageToContact($contactId, $this->messageContent, $this->source);
            
            // Mark message as synced if successful
            if ($success) {
                Message::where('instagram_user_id', $this->userId)
                    ->where('message_text', $this->messageContent)
                    ->where('message_type', $this->getMessageTypeFromSource())
                    ->latest()
                    ->first()
                    ?->update(['synced_to_hubspot' => true]);
            }

        } catch (\Exception $e) {
            Log::error('HubSpot Sync Job failed', [
                'user_id' => $this->userId,
                'username' => $this->username,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    protected function getMessageTypeFromSource(): string
    {
        return match($this->source) {
            'Instagram DM' => 'dm',
            'Instagram Comment' => 'comment',
            default => 'dm',
        };
    }
}

