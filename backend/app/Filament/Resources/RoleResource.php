<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShieldCheck;

    protected static ?string $navigationLabel = 'Roller (Spatie)';

    protected static ?string $modelLabel = 'Rol';

    protected static ?string $pluralModelLabel = 'Roller';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Rol Adı')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            TextInput::make('guard_name')
                ->label('Guard')
                ->default('web')
                ->required()
                ->maxLength(255),
            Select::make('permissions')
                ->label('İzinler (Permissions)')
                ->relationship('permissions', 'name')
                ->multiple()
                ->preload()
                ->searchable()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Rol')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->badge(),
                TextColumn::make('permissions.name')
                    ->label('Tanımlı İzinler')
                    ->badge()
                    ->limitList(6),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRoles::route('/'),
        ];
    }
}
