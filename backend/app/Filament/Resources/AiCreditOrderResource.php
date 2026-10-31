<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiCreditOrderResource\Pages;
use App\Models\AiCreditOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiCreditOrderResource extends Resource
{
    protected static ?string $model = AiCreditOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShoppingBag;

    protected static ?string $navigationLabel = 'Kredi Siparişleri';

    protected static ?string $modelLabel = 'Kredi Siparişi';

    protected static ?string $pluralModelLabel = 'Kredi Siparişleri';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Sipariş No')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Kullanıcı')
                    ->searchable(),
                TextColumn::make('package.name')
                    ->label('Paket'),
                TextColumn::make('credits_amount')
                    ->label('Ana Kredi')
                    ->badge(),
                TextColumn::make('bonus_credits')
                    ->label('Bonus Kredi'),
                TextColumn::make('price_paid_usd')
                    ->label('Tutar')
                    ->formatStateUsing(fn ($state): string => '$' . number_format((float) $state, 2)),
                TextColumn::make('status')
                    ->label('Durum')
                    ->badge(),
                TextColumn::make('whop_payment_id')
                    ->label('Whop Payment ID')
                    ->searchable(),
                TextColumn::make('paid_at')
                    ->label('Ödeme Tarihi')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAiCreditOrders::route('/'),
        ];
    }
}
