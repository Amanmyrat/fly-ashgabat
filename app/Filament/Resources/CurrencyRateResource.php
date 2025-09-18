<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyRateResource\Pages;
use App\Filament\Resources\CurrencyRateResource\RelationManagers;
use App\Models\CurrencyRate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CurrencyRateResource extends Resource
{
    protected static ?string $model = CurrencyRate::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Currency Rates';

    protected static ?string $modelLabel = 'Currency Rate';

    protected static ?string $pluralModelLabel = 'Currency Rates';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('USD Currency Conversion Rate')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('from_currency')
                                    ->label('From Currency')
                                    ->default('USD')
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('Fixed: US Dollar'),

                                Forms\Components\Select::make('to_currency')
                                    ->label('To Currency')
                                    ->options(\App\Models\CurrencyRate::SUPPORTED_CURRENCIES)
                                    ->default('RUB')
                                    ->required()
                                    ->reactive()
                                    ->helperText('Select the target currency for conversion'),
                            ]),

                        Forms\Components\TextInput::make('rate')
                            ->label(function (callable $get) {
                                $toCurrency = $get('to_currency') ?? 'RUB';
                                return "USD to {$toCurrency} Exchange Rate";
                            })
                            ->numeric()
                            ->step('any')
                            ->inputMode('decimal')
                            ->required()
                            ->helperText(function (callable $get) {
                                $toCurrency = $get('to_currency') ?? 'RUB';
                                $currencyName = \App\Models\CurrencyRate::SUPPORTED_CURRENCIES[$toCurrency] ?? $toCurrency;
                                $example = match($toCurrency) {
                                    'RUB' => '83',
                                    'KZT' => '450',
                                    'UZS' => '12500',
                                    default => '100'
                                };
                                return "Enter how many {$toCurrency} equals 1 USD (e.g., {$example} means 1 USD = {$example} {$toCurrency})";
                            })
                            ->formatStateUsing(function ($state) {
                                if ($state === null) return null;
                                // Remove trailing zeros for display
                                return rtrim(rtrim(number_format($state, 6, '.', ''), '0'), '.');
                            }),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active rates will be used for conversions'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('to_currency')
                    ->label('Currency Pair')
                    ->formatStateUsing(function ($state, $record) {
                        return "USD → {$state}";
                    })
                    ->badge()
                    ->color(function ($state) {
                        return match($state) {
                            'RUB' => 'success',
                            'KZT' => 'warning',
                            'UZS' => 'info',
                            default => 'gray'
                        };
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('rate')
                    ->label('Exchange Rate')
                    ->numeric(
                        decimalPlaces: 0,
                        decimalSeparator: '.',
                        thousandsSeparator: ',',
                    )
                    ->formatStateUsing(function ($state, $record) {
                        // Remove trailing zeros and unnecessary decimals
                        $formatted = rtrim(rtrim(number_format($state, 6, '.', ''), '0'), '.');
                        $symbol = \App\Models\CurrencyRate::getCurrencySymbol($record->to_currency);
                        return "1 USD = {$formatted} {$symbol}";
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),

                Tables\Filters\SelectFilter::make('to_currency')
                    ->label('Target Currency')
                    ->options(\App\Models\CurrencyRate::SUPPORTED_CURRENCIES),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencyRates::route('/'),
            'create' => Pages\CreateCurrencyRate::route('/create'),
            'edit' => Pages\EditCurrencyRate::route('/{record}/edit'),
        ];
    }
}
