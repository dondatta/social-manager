<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use App\Models\AutomationSetting;
use BackedEnum;

class HubSpotIntegration extends Page
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationLabel = 'HubSpot Integration';
    protected static ?string $title = 'HubSpot Integration';
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';

    protected string $view = 'filament.pages.hubspot-integration';

    public ?array $data = [];

    public function mount(): void
    {
        try {
            $settings = AutomationSetting::all()->pluck('value', 'key')->toArray();
        } catch (\Exception $e) {
            \Log::error('HubSpotIntegration mount error: ' . $e->getMessage());
            $settings = [];
        }
        
        // Load HubSpot config from .env/config
        $settings['hubspot_api_key'] = config('services.hubspot.api_key', '');
        $settings['hubspot_instagram_handle_property'] = config('services.hubspot.instagram_handle_property', 'Instagram');
        
        $this->form->fill($settings);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('HubSpot Configuration')
                    ->description('Connect your HubSpot account to automatically log Instagram messages to your contacts.')
                    ->schema([
                        TextInput::make('hubspot_api_key')
                            ->label('HubSpot API Key')
                            ->password()
                            ->helperText('Your HubSpot Private App Access Token. Create one at: https://app.hubspot.com/private-apps')
                            ->placeholder('pat-na1-...')
                            ->required(),
                        
                        TextInput::make('hubspot_instagram_handle_property')
                            ->label('Instagram Handle Property Name')
                            ->helperText('The internal property name in HubSpot where Instagram usernames are stored. Default is "Instagram".')
                            ->default('Instagram')
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Save HubSpot settings
        if (isset($data['hubspot_api_key'])) {
            AutomationSetting::updateOrCreate(
                ['key' => 'hubspot_api_key'],
                ['value' => $data['hubspot_api_key']]
            );
        }
        
        if (isset($data['hubspot_instagram_handle_property'])) {
            AutomationSetting::updateOrCreate(
                ['key' => 'hubspot_instagram_handle_property'],
                ['value' => $data['hubspot_instagram_handle_property']]
            );
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->body('HubSpot integration settings have been updated.')
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

