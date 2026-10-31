<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CreditCard;

    protected static ?string $navigationLabel = 'Abonelikler';

    protected static ?string $modelLabel = 'Abonelik';

    protected static ?string $pluralModelLabel = 'Abonelikler';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Kullanıcı')
                ->options(fn () => User::query()->orderBy('email')->pluck('email', 'id')->toArray())
                ->searchable()
                ->required(),
            Select::make('plan_id')
                ->label('Plan')
                ->options(fn () => Plan::query()->orderBy('sort_order')->pluck('name', 'id')->toArray())
                ->required(),
            Select::make('status')
                ->label('Durum')
                ->options([
                    'active' => 'Active',
                    'cancelled' => 'Cancelled',
                    'expired' => 'Expired',
                ])
                ->default('active')
                ->required(),
            Select::make('source')
                ->label('Kaynak')
                ->options([
                    Subscription::SOURCE_DIRECT => 'Direct',
                    Subscription::SOURCE_WHOP => 'Whop Checkout',
                    Subscription::SOURCE_UPGRADE => 'Upgrade',
                ])
                ->default(Subscription::SOURCE_DIRECT)
                ->required(),
            TextInput::make('whop_membership_id')
                ->label('Whop Membership ID')
                ->maxLength(160),
            DateTimePicker::make('trial_ends_at')
                ->label('Deneme Bitiş Tarihi'),
            DateTimePicker::make('ends_at')
                ->label('Abonelik Bitiş Tarihi'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.email')
                    ->label('Kullanıcı')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Durum')
                    ->badge(),
                TextColumn::make('source')
                    ->label('Kaynak')
                    ->badge(),
                TextColumn::make('whop_membership_id')
                    ->label('Whop ID')
                    ->placeholder('—'),
                TextColumn::make('ends_at')
                    ->label('Bitiş Tarihi')
                    ->dateTime()
                    ->placeholder('Süresiz'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSubscriptions::route('/'),
        ];
    }
}
