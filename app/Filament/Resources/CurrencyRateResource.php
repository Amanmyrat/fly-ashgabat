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
                Forms\Components\Section::make('Currency Conversion Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('from_currency')
                                    ->label('From Currency')
                                    ->options([
                                        'RUB' => 'Russian Ruble (RUB)',
                                        'USD' => 'US Dollar (USD)',
                                        'EUR' => 'Euro (EUR)',
                                    ])
                                    ->default('RUB')
                                    ->required(),
                                    
                                Forms\Components\Select::make('to_currency')
                                    ->label('To Currency')
                                    ->options([
                                        'USD' => 'US Dollar (USD)',
                                        'EUR' => 'Euro (EUR)',
                                        'RUB' => 'Russian Ruble (RUB)',
                                    ])
                                    ->default('USD')
                                    ->required(),
                            ]),
                            
                        Forms\Components\TextInput::make('rate')
                            ->label('Exchange Rate')
                            ->numeric()
                            ->step(0.000001)
                            ->required()
                            ->helperText('Example: If 1 RUB = 0.011 USD, enter 0.011'),
                            
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
                Tables\Columns\TextColumn::make('from_currency')
                    ->label('From')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('to_currency')
                    ->label('To')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('rate')
                    ->label('Exchange Rate')
                    ->numeric(6)
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
                Tables\Filters\SelectFilter::make('from_currency')
                    ->label('From Currency')
                    ->options([
                        'RUB' => 'RUB',
                        'USD' => 'USD',
                        'EUR' => 'EUR',
                    ]),
                    
                Tables\Filters\SelectFilter::make('to_currency')
                    ->label('To Currency')
                    ->options([
                        'USD' => 'USD',
                        'EUR' => 'EUR',
                        'RUB' => 'RUB',
                    ]),
                    
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
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
