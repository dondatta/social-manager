<?php

namespace App\Filament\Resources\AutomationLogs\Pages;

use App\Filament\Resources\AutomationLogs\AutomationLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAutomationLogs extends ManageRecords
{
    protected static string $resource = AutomationLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
