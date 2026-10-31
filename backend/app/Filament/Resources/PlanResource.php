<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Sparkles;

    protected static ?string $navigationLabel = 'Planlar (Free / Pro)';

    protected static ?string $modelLabel = 'Plan';

    protected static ?string $pluralModelLabel = 'Üyelik Planları';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Plan Adı')
                ->required()
                ->maxLength(160),
            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(120),
            Select::make('role_id')
                ->label('Atanacak Spatie Rolü')
                ->options(fn () => Role::query()->orderBy('id')->pluck('name', 'id')->toArray())
                ->required(),
            TextInput::make('whop_plan_id')
                ->label('Whop Plan / Product ID')
                ->placeholder('plan_xxxxxxxxxxxx veya prod_xxxxxxxxxxxx')
                ->helperText('Whop Dashboard > Products altındaki "plan_" veya "prod_" ID.')
                ->maxLength(160),
            TextInput::make('price')
                ->label('Fiyat')
                ->numeric()
                ->default(0.00)
                ->required(),
            TextInput::make('price_suffix')
                ->label('Fiyat Periyodu')
                ->default('/month')
                ->maxLength(40),
            TextInput::make('currency')
                ->label('Para Birimi')
                ->default('USD')
                ->maxLength(3),
            TextInput::make('monthly_points')
                ->label('Aylık AI Kredisi')
                ->numeric()
                ->default(250),
            TextInput::make('bonus_points')
                ->label('Hoşgeldin / Bonus AI Kredisi')
                ->numeric()
                ->default(0),
            TagsInput::make('features')
                ->label('Plan Özellikleri')
                ->placeholder('Özellik ekle ve Enter bas'),
            TextInput::make('cta_text')
                ->label('Buton Metni')
                ->default('Get Started')
                ->maxLength(80),
            TextInput::make('sort_order')
                ->label('Sıralama')
                ->numeric()
                ->default(0),
            Toggle::make('is_highlighted')
                ->label('Öne Çıkan (En Popüler)')
                ->default(false),
            Toggle::make('is_active')
                ->label('Aktif')
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
                    ->label('Plan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role.name')
                    ->label('Spatie Rolü')
                    ->badge()
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Fiyat')
                    ->formatStateUsing(fn ($state, Plan $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->price_suffix)
                    ->badge(),
                TextColumn::make('monthly_points')
                    ->label('Aylık Kredi')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0) . ' Kredi'),
                TextColumn::make('whop_plan_id')
                    ->label('Whop Plan ID')
                    ->placeholder('—'),
                IconColumn::make('is_highlighted')
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
            'index' => Pages\ManagePlans::route('/'),
        ];
    }
}
