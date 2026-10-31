<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiCreditPackageResource\Pages;
use App\Models\AiCreditPackage;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiCreditPackageResource extends Resource
{
    protected static ?string $model = AiCreditPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Bolt;

    protected static ?string $navigationLabel = 'AI Kredi Paketleri';

    protected static ?string $modelLabel = 'AI Kredi Paketi';

    protected static ?string $pluralModelLabel = 'AI Kredi Paketleri';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(80),
            TextInput::make('name')
                ->label('Paket Adı')
                ->required()
                ->maxLength(120),
            TextInput::make('credits')
                ->label('Ana Kredi Miktarı')
                ->numeric()
                ->default(500)
                ->required(),
            TextInput::make('bonus_credits')
                ->label('Bonus Kredi')
                ->numeric()
                ->default(0)
                ->required(),
            TextInput::make('price_usd')
                ->label('Fiyat (USD)')
                ->numeric()
                ->default(9.00)
                ->required(),
            TextInput::make('whop_product_id')
                ->label('Whop Product ID (prod_...)')
                ->placeholder('prod_xxxxxxxxxxxx')
                ->helperText('Whop Dashboard > Products altındaki "prod_" ile başlayan Ürün ID.')
                ->maxLength(120),
            TextInput::make('whop_plan_id')
                ->label('Whop Plan ID (plan_...)')
                ->placeholder('plan_xxxxxxxxxxxx')
                ->helperText('Whop Elements gömülü ödeme penceresi için "plan_" (veya "prod_") ID.')
                ->maxLength(120),
            TextInput::make('whop_checkout_url')
                ->label('Whop Checkout URL (Opsiyonel)')
                ->placeholder('https://whop.com/checkout/plan_xxxxxxxxxxxx/')
                ->maxLength(255),
            Toggle::make('is_popular')
                ->label('En Popüler Paket')
                ->default(false),
            Toggle::make('is_active')
                ->label('Satışta / Aktif')
                ->default(true),
            Textarea::make('description')
                ->label('Açıklama')
                ->rows(2)
                ->maxLength(500),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Paket')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('credits')
                    ->label('Ana Kredi')
                    ->sortable(),
                TextColumn::make('bonus_credits')
                    ->label('Bonus'),
                TextColumn::make('price_usd')
                    ->label('Fiyat')
                    ->formatStateUsing(fn ($state): string => '$' . number_format((float) $state, 2))
                    ->badge(),
                TextColumn::make('whop_product_id')
                    ->label('Whop Product ID')
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('whop_plan_id')
                    ->label('Whop Plan ID')
                    ->placeholder('—')
                    ->copyable(),
                IconColumn::make('is_popular')
                    ->label('Popüler')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAiCreditPackages::route('/'),
        ];
    }
}
