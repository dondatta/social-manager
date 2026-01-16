<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\InstagramService;
use App\Models\AutomationSetting;
use App\Models\AutomationLog;
use App\Models\AutomationCooldown;

class SendWelcomeDmJob implements ShouldQueue
{
    use Queueable;

    public string $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $userId)
    {
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(InstagramService $instagramService): void
    {
        $type = 'welcome_dm';
        
        // Cooldown Check
         $cooldown = AutomationCooldown::where('instagram_user_id', $this->userId)
            ->where('action_type', $type)
            ->where('expires_at', '>', now())
            ->exists();

        if ($cooldown) return;

        $template = AutomationSetting::where('key', 'welcome_dm_template')->value('value');
        if (!$template) return;

        // Personalization
        $profile = $instagramService->getUserProfile($this->userId);
        $firstName = $profile['first_name'] ?? 'there';
        $message = str_replace('{first_name}', $firstName, $template);

        // Send
        $result = $instagramService->sendDm($this->userId, $message);
        $success = $result['success'];

        // Log
        AutomationLog::create([
            'instagram_user_id' => $this->userId,
            'action_type' => $type,
            'status' => $success ? 'success' : 'failed',
            'error_message' => $success ? null : 'Failed to send Welcome DM',
        ]);

        if ($success) {
            AutomationCooldown::create([
                'instagram_user_id' => $this->userId,
                'action_type' => $type,
                'expires_at' => now()->addHours(12),
            ]);
        }
    }
}
