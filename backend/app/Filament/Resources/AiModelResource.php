<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiModelResource\Pages;
use App\Models\AiModel;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiModelResource extends Resource
{
    protected static ?string $model = AiModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CpuChip;

    protected static ?string $navigationLabel = 'AI Modelleri & Fiyatlama';

    protected static ?string $modelLabel = 'AI Modeli';

    protected static ?string $pluralModelLabel = 'AI Modelleri';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('provider_slug')
                ->label('Sağlayıcı')
                ->options([
                    'nebius' => 'Nebius Token Factory',
                    'gemini_compat' => 'Google Gemini',
                    'openai' => 'OpenAI',
                ])
                ->default('nebius')
                ->required(),
            TextInput::make('display_name')
                ->label('Görünen Ad')
                ->required()
                ->maxLength(160),
            TextInput::make('model_id')
                ->label('Model ID')
                ->required()
                ->maxLength(200),
            Select::make('task_role')
                ->label('Görev Rolü')
                ->options([
                    'architect' => 'Circuit Architect & Code Mode',
                    'fast_diagnose' => 'Fast Diagnostics',
                    'datasheet_synth' => 'Datasheet Synthesizer',
                ])
                ->default('architect')
                ->required(),
            TextInput::make('input_cost_per_1m')
                ->label('Girdi Maliyeti ($ / 1M Token)')
                ->numeric()
                ->default(0.20),
            TextInput::make('output_cost_per_1m')
                ->label('Çıktı Maliyeti ($ / 1M Token)')
                ->numeric()
                ->default(0.60),
            TextInput::make('input_multiplier')
                ->label('Girdi Kâr Çarpanı')
                ->numeric()
                ->default(2.0),
            TextInput::make('output_multiplier')
                ->label('Çıktı Kâr Çarpanı')
                ->numeric()
                ->default(2.5),
            Toggle::make('supports_function_calling')
                ->label('Function Calling / Araç Çağrısı')
                ->default(true),
            Toggle::make('supports_code_mode')
                ->label('Code Mode (CircuitSDK)')
                ->default(true),
            Toggle::make('is_default')
                ->label('Varsayılan Model')
                ->default(false),
            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Model')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provider_slug')
                    ->label('Sağlayıcı')
                    ->badge(),
                TextColumn::make('model_id')
                    ->label('Model ID')
                    ->searchable(),
                TextColumn::make('input_cost_per_1m')
                    ->label('Girdi ($/1M)')
                    ->sortable(),
                TextColumn::make('output_cost_per_1m')
                    ->label('Çıktı ($/1M)')
                    ->sortable(),
                TextColumn::make('input_multiplier')
                    ->label('Kâr Çarpanı'),
                IconColumn::make('supports_code_mode')
                    ->label('Code Mode')
                    ->boolean(),
                IconColumn::make('is_default')
                    ->label('Varsayılan')
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
            'index' => Pages\ManageAiModels::route('/'),
        ];
    }
}
