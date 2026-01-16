<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
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
    protected static ?string $navigationLabel = 'Settings (Legacy)';
    protected static ?string $title = 'Settings (Legacy)';
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';
    protected static bool $shouldRegisterNavigation = false; // Hide from navigation

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        try {
            $settings = AutomationSetting::all()->pluck('value', 'key')->toArray();
        } catch (\Exception $e) {
            \Log::error('Settings mount error: ' . $e->getMessage());
            $settings = [];
        }
        
        // Also load HubSpot config from .env/config
        $settings['hubspot_api_key'] = config('services.hubspot.api_key', '');
        $settings['hubspot_instagram_handle_property'] = config('services.hubspot.instagram_handle_property', 'instagram_handle');
        
        // Load Instagram Access Token from .env or database
        $settings['instagram_access_token'] = config('services.instagram.access_token', '');
        $settings['instagram_page_id'] = config('services.instagram.page_id', '');
        
        // Load Expose URL if saved
        $settings['expose_url'] = $settings['expose_url'] ?? '';
        
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

                Section::make('Webhook Configuration')
                    ->description('Instagram webhook setup for receiving messages, comments, and mentions')
                    ->schema([
                        Placeholder::make('local_webhook_url')
                            ->label('Local Webhook URL')
                            ->content(fn () => url('/webhooks/instagram'))
                            ->helperText('This is your local webhook endpoint. Use Expose to make it publicly accessible.'),
                        
                        TextInput::make('expose_url')
                            ->label('Expose URL (Optional)')
                            ->placeholder('https://social-manager.sharedwithexpose.com')
                            ->helperText('Enter your Expose URL here (without /webhooks/instagram). Run: expose share http://social-manager.test --subdomain=social-manager')
                            ->suffixAction(
                                Action::make('copy_webhook')
                                    ->label('Copy Full URL')
                                    ->icon('heroicon-o-clipboard')
                                    ->action(function ($livewire) {
                                        $exposeUrl = $livewire->data['expose_url'] ?? '';
                                        if ($exposeUrl) {
                                            $fullUrl = rtrim($exposeUrl, '/') . '/webhooks/instagram';
                                            \Illuminate\Support\Facades\Session::flash('webhook_url', $fullUrl);
                                            Notification::make()
                                                ->title('Webhook URL Copied!')
                                                ->body('Use this in Facebook: ' . $fullUrl)
                                                ->success()
                                                ->send();
                                        }
                                    })
                            ),
                        
                        Placeholder::make('full_webhook_url')
                            ->label('Full Webhook URL for Facebook')
                            ->content(function ($livewire) {
                                $exposeUrl = $livewire->data['expose_url'] ?? '';
                                if ($exposeUrl) {
                                    return rtrim($exposeUrl, '/') . '/webhooks/instagram';
                                }
                                return 'Enter Expose URL above to see full webhook URL';
                            })
                            ->helperText('Copy this URL and use it in Facebook Developers → Webhooks → Instagram'),
                        
                        Placeholder::make('verify_token')
                            ->label('Verify Token')
                            ->content(fn () => config('services.instagram.verify_token') ?: 'Not set')
                            ->helperText('Use this exact value when setting up the webhook in Facebook'),
                        
                        Placeholder::make('webhook_status')
                            ->label('Configuration Status')
                            ->content(fn () => $this->getWebhookStatus()),
                    ])
                    ->collapsible(),

                Section::make('Instagram API Configuration')
                    ->description('Manage your Instagram access token and page ID. Update these when your token expires.')
                    ->schema([
                        Placeholder::make('token_instructions')
                            ->label('How to Get Your Access Token')
                            ->content(function () {
                                return view('filament.pages.instagram-token-instructions');
                            })
                            ->columnSpanFull(),
                        
                        TextInput::make('instagram_access_token')
                            ->label('Instagram Access Token')
                            ->password()
                            ->helperText('Paste your Instagram Page Access Token here. It should start with "EAA..."')
                            ->placeholder('EAA...')
                            ->suffixAction(
                                Action::make('test_token')
                                    ->label('Test Token')
                                    ->icon('heroicon-o-check-circle')
                                    ->action(function ($livewire) {
                                        $token = $livewire->data['instagram_access_token'] ?? '';
                                        if ($token) {
                                            $this->testInstagramToken($token);
                                        } else {
                                            Notification::make()
                                                ->title('No Token Provided')
                                                ->body('Please enter an access token first.')
                                                ->warning()
                                                ->send();
                                        }
                                    })
                            ),
                        
                        TextInput::make('instagram_page_id')
                            ->label('Instagram Page ID')
                            ->helperText('Your Instagram Business Account Page ID. You can find this in your Facebook Page settings or it will be shown when you generate a token.')
                            ->placeholder('1234567890'),
                    ])
                    ->collapsible(),

                Section::make('HubSpot Integration')
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

        // Handle Instagram token and page ID - save to database and update config
        if (isset($data['instagram_access_token']) && !empty($data['instagram_access_token'])) {
            AutomationSetting::updateOrCreate(
                ['key' => 'instagram_access_token'],
                ['value' => $data['instagram_access_token']]
            );
            // Update config cache
            config(['services.instagram.access_token' => $data['instagram_access_token']]);
        }
        
        if (isset($data['instagram_page_id']) && !empty($data['instagram_page_id'])) {
            AutomationSetting::updateOrCreate(
                ['key' => 'instagram_page_id'],
                ['value' => $data['instagram_page_id']]
            );
            // Update config cache
            config(['services.instagram.page_id' => $data['instagram_page_id']]);
        }

        // Handle HubSpot settings
        foreach ($data as $key => $value) {
            // Skip Instagram and HubSpot settings - they need special handling
            if (in_array($key, ['hubspot_api_key', 'hubspot_instagram_handle_property', 'instagram_access_token', 'instagram_page_id'])) {
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
            ->body('Instagram access token has been updated. The new token will be used immediately.')
            ->success()
            ->send();
    }

    protected function testInstagramToken(string $token): void
    {
        try {
            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->get('https://graph.facebook.com/v21.0/me', [
                    'fields' => 'id,name',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Notification::make()
                    ->title('Token is Valid ✓')
                    ->body('Connected to: ' . ($data['name'] ?? $data['id'] ?? 'Unknown'))
                    ->success()
                    ->send();
            } else {
                $error = $response->json();
                $errorMessage = $error['error']['message'] ?? 'Unknown error';
                Notification::make()
                    ->title('Token is Invalid ✗')
                    ->body($errorMessage)
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error Testing Token')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getWebhookStatus(): string
    {
        $accessToken = config('services.instagram.access_token');
        $pageId = config('services.instagram.page_id');
        $verifyToken = config('services.instagram.verify_token');
        $appSecret = config('services.instagram.app_secret');

        $status = [];
        
        if ($accessToken) {
            $status[] = '✓ Access Token: Set';
        } else {
            $status[] = '✗ Access Token: Missing';
        }

        if ($pageId) {
            $status[] = '✓ Page ID: Set';
        } else {
            $status[] = '✗ Page ID: Missing';
        }

        if ($verifyToken) {
            $status[] = '✓ Verify Token: Set';
        } else {
            $status[] = '✗ Verify Token: Missing';
        }

        if ($appSecret) {
            $status[] = '✓ App Secret: Set';
        } else {
            $status[] = '✗ App Secret: Missing';
        }

        return implode(' | ', $status);
    }
}
