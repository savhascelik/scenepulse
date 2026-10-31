<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleLimitResource\Pages;
use App\Models\RoleLimit;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RoleLimitResource extends Resource
{
    protected static ?string $model = RoleLimit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Rol Limit Değişkenleri';

    protected static ?string $modelLabel = 'Rol Limiti';

    protected static ?string $pluralModelLabel = 'Rol Limit Değişkenleri';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('role_id')
                ->label('Rol')
                ->options(fn () => Role::pluck('name', 'id')->toArray())
                ->searchable()
                ->required(),
            Select::make('resource_type')
                ->label('Kaynak Türü')
                ->options(fn () => RoleLimit::getAvailableResources())
                ->default(RoleLimit::RESOURCE_AI_USAGE)
                ->live()
                ->required(),
            Select::make('limit_key')
                ->label('Limit Anahtarı')
                ->options(function (callable $get): array {
                    $resourceType = $get('resource_type');

                    return RoleLimit::getLimitKeys($resourceType ?: null);
                })
                ->default(RoleLimit::LIMIT_INITIAL_CREDIT_BALANCE)
                ->required(),
            TextInput::make('limit_value')
                ->label('Değer (-1 = Sınırsız)')
                ->numeric()
                ->default(250)
                ->helperText('-1 girerseniz sınırsız (∞ Unlimited) olarak uygulanır.')
                ->required(),
            Textarea::make('description')
                ->label('Açıklama')
                ->rows(2)
                ->maxLength(500),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup('role.name')
            ->columns([
                TextColumn::make('role.name')
                    ->label('Rol')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'pro' => 'warning',
                        'free' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('resource_type')
                    ->label('Kaynak')
                    ->badge(),
                TextColumn::make('limit_key')
                    ->label('Limit Değişkeni')
                    ->searchable(),
                TextColumn::make('limit_value')
                    ->label('Değer')
                    ->formatStateUsing(fn (int $state): string => $state === -1 ? '∞ Sınırsız' : (string) $state)
                    ->badge()
                    ->color(fn (int $state): string => $state === -1 ? 'success' : 'gray'),
                TextColumn::make('description')
                    ->label('Açıklama')
                    ->limit(50),
            ])
            ->filters([
                SelectFilter::make('role_id')
                    ->label('Rol')
                    ->options(fn () => Role::pluck('name', 'id')->toArray()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRoleLimits::route('/'),
        ];
    }
}
