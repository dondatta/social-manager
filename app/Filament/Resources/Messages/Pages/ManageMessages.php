<?php

namespace App\Filament\Resources\Messages\Pages;

use App\Filament\Resources\Messages\MessageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Notifications\Notification;

class ManageMessages extends ManageRecords
{
    protected static string $resource = MessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
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


