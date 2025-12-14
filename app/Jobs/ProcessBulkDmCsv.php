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
            
            // Dispatch individual job for each user to avoid blocking and allow better rate limiting
            // Pass necessary data to the new job
            SendSingleDmJob::dispatch($userId, $this->messageTemplate, $row, $header)
                ->delay(now()->addSeconds(1)); // Basic spacing, or let queue worker handle it
        }

        fclose($file);
    }
}


