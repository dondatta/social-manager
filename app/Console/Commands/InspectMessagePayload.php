<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Message;

class InspectMessagePayload extends Command
{
    protected $signature = 'message:inspect {message_id : The message ID to inspect}';
    protected $description = 'Inspect the raw webhook payload for a message to see what data is available';

    public function handle()
    {
        $messageId = $this->argument('message_id');
        $message = Message::find($messageId);
        
        if (!$message) {
            $this->error("Message with ID {$messageId} not found.");
            return 1;
        }
        
        $this->info("Message ID: {$message->id}");
        $this->info("User ID: {$message->instagram_user_id}");
        $this->info("Username: " . ($message->instagram_username ?: 'NULL'));
        $this->info("Profile Pic: " . ($message->profile_picture_url ?: 'NULL'));
        $this->info("Type: {$message->message_type}");
        $this->newLine();
        
        if ($message->raw_payload) {
            $this->info("Raw Payload:");
            $this->line(json_encode($message->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            
            // Try to extract user info from payload
            $this->newLine();
            $this->info("Extracted Information:");
            
            if (isset($message->raw_payload['sender'])) {
                $this->line("Sender: " . json_encode($message->raw_payload['sender'], JSON_PRETTY_PRINT));
            }
            
            if (isset($message->raw_payload['from'])) {
                $this->line("From: " . json_encode($message->raw_payload['from'], JSON_PRETTY_PRINT));
            }
            
            // Check for username in nested structures
            $payload = $message->raw_payload;
            $username = $payload['from']['username'] ?? $payload['sender']['username'] ?? $payload['username'] ?? null;
            if ($username) {
                $this->info("Found username in payload: {$username}");
            }
        } else {
            $this->warn("No raw payload available for this message.");
        }
        
        return 0;
    }
}

