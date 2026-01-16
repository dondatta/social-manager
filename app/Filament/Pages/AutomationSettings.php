<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use App\Models\AutomationSetting;
use BackedEnum;

class AutomationSettings extends Page
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Automation Settings';
    protected static ?string $title = 'Automation Settings';
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';

    protected string $view = 'filament.pages.automation-settings';

    public ?array $data = [];

    public function mount(): void
    {
        try {
            $settings = AutomationSetting::all()->pluck('value', 'key')->toArray();
        } catch (\Exception $e) {
            \Log::error('AutomationSettings mount error: ' . $e->getMessage());
            $settings = [];
        }
        
        $this->form->fill($settings);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('story_reply_template')
                    ->label('Story Reply DM Template')
                    ->helperText('Message sent when someone replies to your story. Use {first_name} for personalization.')
                    ->required()
                    ->placeholder('Thanks for replying, {first_name}!'),
                
                TextInput::make('welcome_dm_delay')
                    ->label('Welcome DM Delay (minutes)')
                    ->numeric()
                    ->default(5)
                    ->required()
                    ->helperText('How long to wait before sending a welcome DM to new followers.'),
                
                TextInput::make('welcome_dm_template')
                    ->label('New Follower Welcome DM Template')
                    ->helperText('Message sent to new followers. Use {first_name} for personalization.')
                    ->required()
                    ->placeholder('Welcome, {first_name}! Thanks for following us.'),
                
                TextInput::make('comment_keyword')
                    ->label('Comment Keyword')
                    ->placeholder('GUIDE')
                    ->required()
                    ->helperText('When someone comments this keyword on your post, they will receive an automated DM.'),
                
                TextInput::make('comment_dm_template')
                    ->label('Comment DM Template')
                    ->helperText('Message sent when someone comments the keyword. Use {first_name} for personalization.')
                    ->required()
                    ->placeholder('Hi {first_name}! Here is your guide...'),
                
                TextInput::make('story_mention_template')
                    ->label('Story Mention DM Template')
                    ->helperText('Message sent when someone mentions you in their story. Use {first_name} for personalization.')
                    ->required()
                    ->placeholder('Thanks for the mention, {first_name}!'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            AutomationSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
    
    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }
}

