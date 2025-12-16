<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Message;
use App\Services\InstagramService;
use Illuminate\Support\Facades\Log;

class UpdateMessageProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:update-profiles {--limit=50 : Number of messages to update}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing messages with usernames and profile pictures from Instagram API';

    /**
     * Execute the console command.
     */
    public function handle(InstagramService $instagramService)
    {
        $limit = (int) $this->option('limit');
        
        $this->info("Fetching messages that need profile updates...");
        
        // Get messages without username or profile picture (null or empty)
        $messages = Message::where(function($query) {
            $query->whereNull('instagram_username')
                  ->orWhere('instagram_username', '')
                  ->orWhereNull('profile_picture_url')
                  ->orWhere('profile_picture_url', '');
        })
        ->whereNotNull('instagram_user_id')
        ->where('instagram_user_id', '!=', '')
        ->limit($limit)
        ->get();
        
        if ($messages->isEmpty()) {
            $this->info('No messages need updating.');
            return 0;
        }
        
        $this->info("Found {$messages->count()} messages to update.");
        $bar = $this->output->createProgressBar($messages->count());
        $bar->start();
        
        $updated = 0;
        $failed = 0;
        
        foreach ($messages as $message) {
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
                        $updated++;
                    }
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to update message profile', [
                    'message_id' => $message->id,
                    'user_id' => $message->instagram_user_id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
            
            $bar->advance();
            
            // Rate limiting - wait 1 second between API calls
            sleep(1);
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("Updated: {$updated} messages");
        if ($failed > 0) {
            $this->warn("Failed: {$failed} messages");
        }
        
        return 0;
    }
}
