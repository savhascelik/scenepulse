<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiCreditTransactionResource\Pages;
use App\Models\AiCreditTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiCreditTransactionResource extends Resource
{
    protected static ?string $model = AiCreditTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static ?string $navigationLabel = 'Kredi Muhasebe Hareketleri';

    protected static ?string $modelLabel = 'Kredi Hareketi';

    protected static ?string $pluralModelLabel = 'Kredi Muhasebe Hareketleri';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#ID')
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Kullanıcı')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tür')
                    ->badge(),
                TextColumn::make('bucket')
                    ->label('Bakiye Kovası')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('Miktar')
                    ->formatStateUsing(fn ($state): string => ((float) $state >= 0 ? '+' : '') . rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',') . ' Kredi')
                    ->badge()
                    ->color(fn ($state): string => (float) $state >= 0 ? 'success' : 'danger'),
                TextColumn::make('balance_after')
                    ->label('İşlem Sonrası Bakiye')
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',') . ' Kredi'),
                TextColumn::make('description')
                    ->label('Açıklama')
                    ->limit(60),
                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime()
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAiCreditTransactions::route('/'),
        ];
    }
}
