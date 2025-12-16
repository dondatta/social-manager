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
        
        // Also load HubSpot config from .env/config
        $settings['hubspot_api_key'] = config('services.hubspot.api_key', '');
        $settings['hubspot_instagram_handle_property'] = config('services.hubspot.instagram_handle_property', 'instagram_handle');
        
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

                \Filament\Forms\Components\Section::make('HubSpot Integration')
                    ->description('Create a Private App using HubSpot CLI: `hs apps create private-app`. See HUBSPOT_SETUP.md for details.')
                    ->schema([
                        TextInput::make('hubspot_api_key')
                            ->label('HubSpot API Key')
                            ->password()
                            ->helperText('Your HubSpot Private App Access Token (created via CLI: hs apps create private-app)')
                            ->placeholder('pat-na1-...'),
                        
                        TextInput::make('hubspot_instagram_handle_property')
                            ->label('Instagram Handle Property Name')
                            ->helperText('The internal property name in HubSpot where Instagram usernames are stored (default: "Instagram")')
                            ->default('Instagram')
                            ->required(),
                    ])
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Handle HubSpot settings separately (they go to .env, not database)
        $hubspotApiKey = $data['hubspot_api_key'] ?? '';
        $hubspotProperty = $data['hubspot_instagram_handle_property'] ?? 'instagram_handle';
        
        // Note: In production, you'd want to update .env file programmatically
        // For now, we'll save to AutomationSetting and user can manually update .env
        // Or we can use a package like dotenv-editor
        
        foreach ($data as $key => $value) {
            // Skip HubSpot settings - they need special handling
            if (in_array($key, ['hubspot_api_key', 'hubspot_instagram_handle_property'])) {
                AutomationSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
                continue;
            }
            
            AutomationSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->body('Note: HubSpot API key changes require updating your .env file and restarting the application.')
            ->success()
            ->send();
    }
}
