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
                TextColumn::make('instagram_username')
                    ->label('Username')
                    ->searchable()
                    ->sortable(),
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

