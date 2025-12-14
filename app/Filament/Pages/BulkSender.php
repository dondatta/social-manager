<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessBulkDmCsv;
use BackedEnum;

class BulkSender extends Page
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-paper-airplane';
    protected static string $view = 'filament.pages.bulk-sender';
    protected static ?string $navigationLabel = 'Bulk DM Sender';
    protected static ?string $title = 'Bulk DM Sender';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('csv_file')
                    ->label('Upload CSV (Instagram User IDs)')
                    ->disk('local')
                    ->directory('bulk-csv')
                    ->acceptedFileTypes(['text/csv', 'text/plain'])
                    ->required()
                    ->helperText('CSV must contain a column named "instagram_id"'),
                
                Textarea::make('message')
                    ->label('Message Template')
                    ->rows(4)
                    ->required()
                    ->helperText('Use {first_name} for personalization. NOTE: You can only send messages to users who engaged with you in the last 24 hours.'),
            ])
            ->statePath('data');
    }

    public function send(): void
    {
        $data = $this->form->getState();
        $filePath = Storage::disk('local')->path($data['csv_file']);
        
        // Dispatch Job
        ProcessBulkDmCsv::dispatch($filePath, $data['message']);

        Notification::make()
            ->title('Bulk Sending Started')
            ->body('The CSV is being processed in the background. Messages will only be sent to eligible users (24h window).')
            ->success()
            ->send();
        
        $this->form->fill();
    }
    
    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('Start Sending')
                ->submit('send'),
        ];
    }
}

