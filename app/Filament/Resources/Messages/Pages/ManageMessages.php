<?php

namespace App\Filament\Resources\Messages\Pages;

use App\Filament\Resources\Messages\MessageResource;
use App\Models\Message;
use App\Jobs\FetchUserProfileJob;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;

class ManageMessages extends ManageRecords
{
    protected static string $resource = MessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh_profiles')
                ->label('Refresh Profiles')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Refresh User Profiles')
                ->modalDescription('This will attempt to fetch usernames and profile pictures for messages that are missing this information. This may take a few moments.')
                ->action(function () {
                    $messages = Message::where(function($query) {
                        $query->whereNull('instagram_username')
                              ->orWhere('instagram_username', '')
                              ->orWhereNull('profile_picture_url')
                              ->orWhere('profile_picture_url', '');
                    })
                    ->whereNotNull('instagram_user_id')
                    ->where('instagram_user_id', '!=', '')
                    ->get();
                    
                    $count = $messages->count();
                    
                    if ($count === 0) {
                        Notification::make()
                            ->title('No profiles to update')
                            ->body('All messages already have usernames and profile pictures.')
                            ->success()
                            ->send();
                        return;
                    }
                    
                    // Dispatch jobs for each message with a delay to avoid rate limiting
                    foreach ($messages as $index => $message) {
                        FetchUserProfileJob::dispatch($message->id)->delay(now()->addSeconds($index));
                    }
                    
                    Notification::make()
                        ->title('Profile refresh started')
                        ->body("Processing {$count} messages. Profiles will be updated shortly.")
                        ->success()
                        ->send();
                }),
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $this->dispatch('$refresh');
                    Notification::make()
                        ->title('Messages refreshed')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTitle(): string
    {
        $count = \App\Models\Message::count();
        return "Messages ({$count})";
    }
}


