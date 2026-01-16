<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\AutomationSetting;
use BackedEnum;

class InstagramConfiguration extends Page
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationLabel = 'Instagram Configuration';
    protected static ?string $title = 'Instagram Configuration';
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';

    protected string $view = 'filament.pages.instagram-configuration';

    public ?array $data = [];

    public function mount(): void
    {
        try {
            $settings = AutomationSetting::all()->pluck('value', 'key')->toArray();
        } catch (\Exception $e) {
            \Log::error('InstagramConfiguration mount error: ' . $e->getMessage());
            $settings = [];
        }
        
        // Load Instagram Access Token from .env or database
        $settings['instagram_access_token'] = config('services.instagram.access_token', '');
        $settings['instagram_page_id'] = config('services.instagram.page_id', '');
        
        $this->form->fill($settings);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Access Token')
                    ->description('Your Instagram Page Access Token. Update this when your token expires (every 60 days).')
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
                            ->helperText('Paste your Instagram Page Access Token here. It should start with "EAA...". Make sure your token includes the instagram_manage_messages permission.')
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

                Section::make('Webhook Configuration')
                    ->description('Configure webhooks to receive Instagram messages, comments, and mentions in real-time.')
                    ->schema([
                        Placeholder::make('webhook_url')
                            ->label('Webhook URL')
                            ->content(fn () => url('/webhooks/instagram'))
                            ->helperText('Copy this URL and use it when setting up your webhook in Facebook Developers.'),
                        
                        Placeholder::make('verify_token')
                            ->label('Verify Token')
                            ->content(fn () => config('services.instagram.verify_token') ?: 'Not set')
                            ->helperText('Use this exact value when setting up the webhook in Facebook Developers.'),
                        
                        Placeholder::make('webhook_setup_instructions')
                            ->label('How to Set Up Webhook in Facebook')
                            ->content(function () {
                                return view('filament.pages.webhook-setup-instructions');
                            })
                            ->columnSpanFull(),
                        
                        Placeholder::make('webhook_status')
                            ->label('Configuration Status')
                            ->content(fn () => $this->getWebhookStatus()),
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
    
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Configuration')
                ->submit('save'),
        ];
    }
}

