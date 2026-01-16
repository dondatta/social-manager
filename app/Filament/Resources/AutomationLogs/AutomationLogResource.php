<?php

namespace App\Filament\Resources\AutomationLogs;

use App\Filament\Resources\AutomationLogs\Pages\ManageAutomationLogs;
use App\Models\AutomationLog;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AutomationLogResource extends Resource
{
    protected static ?string $model = AutomationLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('instagram_user_id')
                    ->required(),
                TextInput::make('action_type')
                    ->required(),
                TextInput::make('status')
                    ->required(),
                Textarea::make('payload')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('error_message')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('instagram_user_id')
                    ->label('User ID')
                    ->searchable()
                    ->copyable()
                    ->limit(20),
                TextColumn::make('action_type')
                    ->label('Action Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'manual_dm', 'bulk_dm' => 'info',
                        'comment_reply' => 'warning',
                        'welcome_dm' => 'success',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('error_message')
                    ->label('Error Message')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->error_message)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('payload')
                    ->label('Details')
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : '')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->payload ? json_encode($record->payload, JSON_PRETTY_PRINT) : '')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->since()
                    ->description(fn ($record) => $record->created_at->format('M j, Y g:i A')),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'success' => 'Success',
                        'failed' => 'Failed',
                    ]),
                \Filament\Tables\Filters\SelectFilter::make('action_type')
                    ->options([
                        'manual_dm' => 'Manual DM',
                        'bulk_dm' => 'Bulk DM',
                        'comment_reply' => 'Comment Reply',
                        'welcome_dm' => 'Welcome DM',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn ($record) => "Log Details - {$record->action_type}")
                    ->modalContent(fn ($record) => view('filament.resources.automation-logs.view-modal', ['record' => $record])),
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
            'index' => ManageAutomationLogs::route('/'),
        ];
    }
}
