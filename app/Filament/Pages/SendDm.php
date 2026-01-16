<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Services\InstagramService;
use App\Models\AutomationLog;
use BackedEnum;

class SendDm extends Page
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected string $view = 'filament.pages.send-dm';
    protected static ?string $navigationLabel = 'Send DM';
    protected static ?string $title = 'Send Direct Message';
    protected static string | \UnitEnum | null $navigationGroup = 'Messaging';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'is_comment_reply' => false,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('recipient_id')
                    ->label('Instagram User ID or Comment ID')
                    ->placeholder('e.g., 5385758574823787')
                    ->required()
                    ->helperText('Enter the Instagram User ID (for DMs) or Comment ID (for comment replies). You can find User IDs from incoming messages.'),
                
                Textarea::make('message')
                    ->label('Message')
                    ->rows(6)
                    ->required()
                    ->placeholder('Type your message here...')
                    ->helperText('You can only send messages to users who have engaged with you in the last 24 hours.'),
                
                Toggle::make('is_comment_reply')
                    ->label('This is a Comment Reply')
                    ->helperText('Enable this if the ID above is a Comment ID (for private replies to comments).')
                    ->default(false),
            ])
            ->statePath('data');
    }

    public function send(): void
    {
        $data = $this->form->getState();
        
        $recipientId = $data['recipient_id'];
        $message = $data['message'];
        $isCommentReply = $data['is_comment_reply'] ?? false;

        $instagramService = new InstagramService();
        
        try {
            $result = $instagramService->sendDm($recipientId, $message, $isCommentReply);
            
            if ($result['success']) {
                // Log the action
                AutomationLog::create([
                    'instagram_user_id' => $recipientId,
                    'action_type' => $isCommentReply ? 'comment_reply' : 'manual_dm',
                    'status' => 'success',
                    'payload' => json_encode([
                        'message' => $message,
                        'is_comment_reply' => $isCommentReply,
                    ]),
                ]);

                Notification::make()
                    ->title('Message Sent Successfully')
                    ->body('Your DM has been sent to the user.')
                    ->success()
                    ->send();
                
                // Clear the form
                $this->form->fill([
                    'is_comment_reply' => false,
                ]);
            } else {
                $errorMessage = $result['error'] ?? 'The message could not be sent. Check that the user has engaged with you in the last 24 hours, or verify the User ID is correct.';
                
                Notification::make()
                    ->title('Failed to Send Message')
                    ->body($errorMessage)
                    ->danger()
                    ->send();
                
                // Log the failure
                AutomationLog::create([
                    'instagram_user_id' => $recipientId,
                    'action_type' => $isCommentReply ? 'comment_reply' : 'manual_dm',
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                    'payload' => json_encode([
                        'message' => $message,
                        'is_comment_reply' => $isCommentReply,
                    ]),
                ]);
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('An error occurred: ' . $e->getMessage())
                ->danger()
                ->send();
            
            AutomationLog::create([
                'instagram_user_id' => $recipientId,
                'action_type' => $isCommentReply ? 'comment_reply' : 'manual_dm',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'payload' => json_encode([
                    'message' => $message,
                    'is_comment_reply' => $isCommentReply,
                ]),
            ]);
        }
    }
    
    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('Send Message')
                ->submit('send')
                ->icon('heroicon-o-paper-airplane'),
        ];
    }
}

