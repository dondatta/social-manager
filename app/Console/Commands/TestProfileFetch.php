<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Message;
use App\Jobs\FetchUserProfileJob;
use App\Services\InstagramService;

class TestProfileFetch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:profile-fetch {message_id? : Specific message ID to test, or leave empty to test first message without profile}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the profile fetching functionality';

    /**
     * Execute the console command.
     */
    public function handle(InstagramService $instagramService)
    {
        $messageId = $this->argument('message_id');
        
        if ($messageId) {
            $message = Message::find($messageId);
            if (!$message) {
                $this->error("Message with ID {$messageId} not found.");
                return 1;
            }
        } else {
            // Find first message without username or profile picture
            $message = Message::where(function($query) {
                $query->whereNull('instagram_username')
                      ->orWhere('instagram_username', '')
                      ->orWhereNull('profile_picture_url')
                      ->orWhere('profile_picture_url', '');
            })
            ->whereNotNull('instagram_user_id')
            ->where('instagram_user_id', '!=', '')
            ->first();
            
            if (!$message) {
                $this->info('No messages found that need profile updates.');
                return 0;
            }
        }
        
        $this->info("Testing profile fetch for Message ID: {$message->id}");
        $this->info("User ID: {$message->instagram_user_id}");
        $this->info("Current Username: " . ($message->instagram_username ?: 'NULL'));
        $this->info("Current Profile Pic: " . ($message->profile_picture_url ?: 'NULL'));
        $this->newLine();
        
        // Test direct API call
        $this->info("Fetching profile from Instagram API...");
        $profile = $instagramService->getUserProfile($message->instagram_user_id);
        
        if ($profile) {
            $this->info("✓ Profile fetched successfully!");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Username', $profile['username'] ?? 'NULL'],
                    ['Profile Pic', $profile['profile_pic'] ?? $profile['profile_picture_url'] ?? 'NULL'],
                    ['First Name', $profile['first_name'] ?? 'NULL'],
                    ['Name', $profile['name'] ?? 'NULL'],
                ]
            );
            
            // Ask if they want to update the message
            if ($this->confirm('Do you want to update this message with the fetched profile?', true)) {
                $updateData = [];
                
                if (!$message->instagram_username && isset($profile['username'])) {
                    $updateData['instagram_username'] = $profile['username'];
                }
                
                $profilePic = $profile['profile_picture_url'] ?? $profile['profile_pic'] ?? null;
                if (!$message->profile_picture_url && $profilePic) {
                    $updateData['profile_picture_url'] = $profilePic;
                }
                
                if (!empty($updateData)) {
                    $message->update($updateData);
                    $this->info("✓ Message updated successfully!");
                } else {
                    $this->info("No updates needed (message already has all data).");
                }
            }
            
            // Ask if they want to test the job
            if ($this->confirm('Do you want to test the FetchUserProfileJob?', false)) {
                $this->info("Dispatching FetchUserProfileJob...");
                FetchUserProfileJob::dispatch($message->id);
                $this->info("✓ Job dispatched! Make sure your queue worker is running to process it.");
            }
        } else {
            $this->error("✗ Failed to fetch profile. Check your Instagram API credentials and permissions.");
            $this->warn("The API might not return profile data for this user ID, or the access token might not have the required permissions.");
        }
        
        return 0;
    }
}

