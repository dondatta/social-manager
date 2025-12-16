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
use Illuminate\Queue\Middleware\RateLimited;

class SendSingleDmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $messageTemplate;
    protected $row;
    protected $header;

    public function __construct($userId, $messageTemplate, $row, $header)
    {
        $this->userId = $userId;
        $this->messageTemplate = $messageTemplate;
        $this->row = $row;
        $this->header = $header;
    }

    public function middleware()
    {
        // Rate limit manually or via Redis if configured.
        // For now, we rely on the queue worker's inherent speed or global limits.
        // Or we can use `RateLimited` if we define a limiter in AppServiceProvider.
        return [];
    }

    public function handle(InstagramService $instagramService)
    {
        try {
            // Personalization (Basic)
            $nameIndex = array_search('name', $this->header);
            $name = ($nameIndex !== false) ? ($this->row[$nameIndex] ?? '') : '';
            
            $firstName = $name ?: 'there';
            
            $message = str_replace('{first_name}', $firstName, $this->messageTemplate);

            $success = $instagramService->sendDm($this->userId, $message);

            AutomationLog::create([
                'instagram_user_id' => $this->userId,
                'action_type' => 'bulk_dm',
                'status' => $success ? 'success' : 'failed',
                'error_message' => $success ? null : 'Failed (Likely outside 24h window or invalid ID)',
            ]);

        } catch (\Exception $e) {
            Log::error("Bulk DM Error for user {$this->userId}: " . $e->getMessage());
        }
    }
}



