<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WebhookLogResource\Pages;
use App\Models\WebhookLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WebhookLogResource extends Resource
{
    protected static ?string $model = WebhookLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Signal;

    protected static ?string $navigationLabel = 'Ödeme Webhook Kayıtları';

    protected static ?string $modelLabel = 'Webhook Kaydı';

    protected static ?string $pluralModelLabel = 'Ödeme Webhook Kayıtları';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#ID')
                    ->sortable(),
                TextColumn::make('provider')
                    ->label('Sağlayıcı')
                    ->badge(),
                TextColumn::make('event_type')
                    ->label('Olay Türü')
                    ->searchable(),
                TextColumn::make('external_id')
                    ->label('Harici ID')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Durum')
                    ->badge(),
                TextColumn::make('processed_at')
                    ->label('İşlenme Tarihi')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageWebhookLogs::route('/'),
        ];
    }
}
