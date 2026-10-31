<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionResource\Pages;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Key;

    protected static ?string $navigationLabel = 'İzinler (Permissions)';

    protected static ?string $modelLabel = 'İzin';

    protected static ?string $pluralModelLabel = 'İzinler';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('İzin Anahtarı')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            TextInput::make('guard_name')
                ->label('Guard')
                ->default('web')
                ->required()
                ->maxLength(255),
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
                    ->label('İzin Anahtarı')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->badge(),
                TextColumn::make('roles.name')
                    ->label('Atanmış Roller')
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePermissions::route('/'),
        ];
    }
}
