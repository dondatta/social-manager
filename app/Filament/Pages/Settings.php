<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use App\Models\AutomationSetting;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use BackedEnum;

class Settings extends Page
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = AutomationSetting::all()->pluck('value', 'key')->toArray();
        $this->form->fill($settings);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('story_reply_template')
                    ->label('Story Reply DM Template')
                    ->helperText('Use {first_name} for user name.')
                    ->required(),
                
                TextInput::make('welcome_dm_delay')
                    ->label('Welcome DM Delay (minutes)')
                    ->numeric()
                    ->default(5)
                    ->required(),

                TextInput::make('welcome_dm_template')
                    ->label('New Follower Welcome DM Template')
                     ->helperText('Use {first_name} for user name.')
                    ->required(),

                TextInput::make('comment_keyword')
                    ->label('Comment Keyword')
                    ->placeholder('GUIDE')
                    ->required(),

                TextInput::make('comment_dm_template')
                    ->label('Comment DM Template')
                     ->helperText('Use {first_name} for user name.')
                    ->required(),

                TextInput::make('story_mention_template')
                    ->label('Story Mention DM Template')
                     ->helperText('Use {first_name} for user name.')
                    ->required(),
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
}
