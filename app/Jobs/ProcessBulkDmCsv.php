<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\InstagramService;
use App\Models\AutomationLog;

class ProcessBulkDmCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $messageTemplate;

    public function __construct($filePath, $messageTemplate)
    {
        $this->filePath = $filePath;
        $this->messageTemplate = $messageTemplate;
    }

    public function handle(InstagramService $instagramService)
    {
        if (!file_exists($this->filePath)) {
            Log::error("Bulk DM Job: CSV file not found at {$this->filePath}");
            return;
        }

        $file = fopen($this->filePath, 'r');
        $header = fgetcsv($file); // Read header row

        // Find the index of 'instagram_id'
        $idIndex = array_search('instagram_id', $header);
        
        if ($idIndex === false) {
             // Fallback: assume first column if header doesn't match
             $idIndex = 0;
        }

        while (($row = fgetcsv($file)) !== false) {
            $userId = $row[$idIndex] ?? null;
            if (!$userId) continue;

            // 24h Engagement Check
            // The Instagram Messaging API enforces a 24-hour policy.
            // "You can only send messages to people who have messaged you in the last 24 hours."
            // However, the API itself will simply ERROR if we try to send outside this window.
            // There is no query to "check" eligibility without trying (or checking our own logs).
            
            // Strategy: Try to send. If it fails due to policy, log it.
            // Ideally, we should check our local DB if we have a recorded incoming interaction.
            
            // For now, we attempt to send.
            
            try {
                // Personalization (Basic)
                // In a real CSV, we might have a 'name' column.
                $nameIndex = array_search('name', $header);
                $name = ($nameIndex !== false) ? ($row[$nameIndex] ?? '') : '';
                
                // If name not in CSV, try fetching? (Expensive for bulk)
                // Let's stick to CSV data or generic fallback.
                $firstName = $name ?: 'there';
                
                $message = str_replace('{first_name}', $firstName, $this->messageTemplate);

                $success = $instagramService->sendDm($userId, $message);

                AutomationLog::create([
                    'instagram_user_id' => $userId,
                    'action_type' => 'bulk_dm',
                    'status' => $success ? 'success' : 'failed',
                    'error_message' => $success ? null : 'Failed (Likely outside 24h window or invalid ID)',
                ]);

                // Rate Limiting Protection
                sleep(1); 

            } catch (\Exception $e) {
                Log::error("Bulk DM Error for user $userId: " . $e->getMessage());
            }
        }

        fclose($file);
    }
}

