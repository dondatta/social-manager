<?php

namespace App\Filament\Resources\Messages;

use App\Filament\Resources\Messages\Pages\ManageMessages;
use App\Models\Message;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;

class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Messages';

    protected static ?string $recordTitleAttribute = 'message_text';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('instagram_user_id')
                    ->label('Instagram User ID')
                    ->required()
                    ->disabled(),
                TextInput::make('instagram_username')
                    ->label('Instagram Username')
                    ->disabled(),
                TextInput::make('profile_picture_url')
                    ->label('Profile Picture URL')
                    ->disabled()
                    ->helperText('Click the image in the table to view the full profile picture'),
                Select::make('message_type')
                    ->label('Message Type')
                    ->options([
                        'dm' => 'Direct Message',
                        'comment' => 'Comment',
                        'story_reply' => 'Story Reply',
                        'story_mention' => 'Story Mention',
                        'mention' => 'Mention',
                    ])
                    ->required()
                    ->disabled(),
                Textarea::make('message_text')
                    ->label('Message Text')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('media_id')
                    ->label('Media ID')
                    ->disabled(),
                TextInput::make('comment_id')
                    ->label('Comment ID')
                    ->disabled(),
                Toggle::make('synced_to_hubspot')
                    ->label('Synced to HubSpot')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message_text')
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('profile_picture_url')
                    ->label('')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl('data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>')),
                TextColumn::make('instagram_username')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->instagram_username ? '@' . $record->instagram_username : 'Unknown')
                    ->description(fn ($record) => $record->instagram_user_id ? 'ID: ' . $record->instagram_user_id : null),
                TextColumn::make('message_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'dm' => 'primary',
                        'comment' => 'success',
                        'story_reply' => 'warning',
                        'story_mention' => 'info',
                        'mention' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'dm' => 'DM',
                        'comment' => 'Comment',
                        'story_reply' => 'Story Reply',
                        'story_mention' => 'Story Mention',
                        'mention' => 'Mention',
                        default => $state,
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('message_text')
                    ->label('Message')
                    ->limit(50)
                    ->searchable()
                    ->wrap(),
                IconColumn::make('synced_to_hubspot')
                    ->label('HubSpot')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('message_type')
                    ->label('Type')
                    ->options([
                        'dm' => 'Direct Message',
                        'comment' => 'Comment',
                        'story_reply' => 'Story Reply',
                        'story_mention' => 'Story Mention',
                        'mention' => 'Mention',
                    ]),
                SelectFilter::make('synced_to_hubspot')
                    ->label('HubSpot Sync')
                    ->options([
                        '1' => 'Synced',
                        '0' => 'Not Synced',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMessages::route('/'),
        ];
    }
}

