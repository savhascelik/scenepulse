<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\AiCreditBalance;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Users;

    protected static ?string $navigationLabel = 'Kullanıcılar & Kredi Bakiyeleri';

    protected static ?string $modelLabel = 'Kullanıcı';

    protected static ?string $pluralModelLabel = 'Kullanıcılar';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Ad Soyad')
                ->required()
                ->maxLength(160),
            TextInput::make('email')
                ->label('E-Posta')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(160),
            TextInput::make('password')
                ->label('Şifre')
                ->password()
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create')
                ->maxLength(255),
            CheckboxList::make('roles')
                ->label('Spatie Kullanıcı Rolleri')
                ->relationship('roles', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record) => match ($record->name) {
                    'free' => 'Free (Ücretsiz Maker)',
                    'pro' => 'Pro (Pro Plan)',
                    'admin' => 'Admin (Site Yöneticisi)',
                    default => ucfirst(str_replace('_', ' ', $record->name)),
                })
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Ad Soyad')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-Posta')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Roller')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'pro' => 'warning',
                        'judge' => 'success',
                        'free' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('scenarios_count')
                    ->counts('scenarios')
                    ->label('Kayıtlı Senaryo')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('creditBalance.promotional_credits')
                    ->label('Promosyon Kredisi')
                    ->default(0)
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',') . ' Kredi')
                    ->sortable(),
                TextColumn::make('creditBalance.purchased_credits')
                    ->label('Satın Alınan Kredi')
                    ->default(0)
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',') . ' Kredi')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Kayıt Tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->description(fn (User $record): string => $record->created_at ? $record->created_at->diffForHumans() : '')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Role Göre Filtrele')
                    ->relationship('roles', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('adjustCredits')
                    ->label('Kredi Tanımla')
                    ->icon(Heroicon::Bolt)
                    ->form([
                        Select::make('bucket')
                            ->label('Bakiye Türü')
                            ->options([
                                'promotional' => 'Promosyon / Rol Kredisi',
                                'purchased' => 'Satın Alınan (Purchased) Kredi',
                            ])
                            ->default('promotional')
                            ->required(),
                        TextInput::make('amount')
                            ->label('Eklenecek Kredi Miktarı')
                            ->numeric()
                            ->minValue(0.01)
                            ->default(250)
                            ->required(),
                        TextInput::make('reason')
                            ->label('Açıklama')
                            ->default('Filament Admin kredi tanımlaması')
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $balance = AiCreditBalance::getOrCreateForUser($record->id);
                        $amount = (float) ($data['amount'] ?? 0);
                        $reason = (string) ($data['reason'] ?? 'Admin adjustment');

                        if (($data['bucket'] ?? 'promotional') === 'purchased') {
                            $balance->addPurchasedCredits($amount, $reason, 'filament_admin', auth()->id());
                        } else {
                            $balance->addPromotionalCredits($amount, 'admin_grant', $reason, 'filament_admin', auth()->id());
                        }

                        Notification::make()
                            ->title("{$record->email} hesabına +{$amount} kredi tanımlandı.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
        ];
    }
}
