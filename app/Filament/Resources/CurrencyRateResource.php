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
    
    protected static ?string $navigationLabel = 'USD to RUB Rates';
    
    protected static ?string $modelLabel = 'USD to RUB Rate';
    
    protected static ?string $pluralModelLabel = 'USD to RUB Rates';
    
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('USD to RUB Conversion Rate')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('from_currency')
                                    ->label('From Currency')
                                    ->default('USD')
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('Fixed: US Dollar'),
                                    
                                Forms\Components\TextInput::make('to_currency')
                                    ->label('To Currency')
                                    ->default('RUB')
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('Fixed: Russian Ruble'),
                            ]),
                            
                        Forms\Components\TextInput::make('rate')
                            ->label('USD to RUB Exchange Rate')
                            ->numeric()
                            ->step(0.01)
                            ->inputMode('decimal')
                            ->required()
                            ->helperText('Enter how many RUB equals 1 USD (e.g., 83 means 1 USD = 83 RUB)')
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
                Tables\Columns\TextColumn::make('rate')
                    ->label('USD → RUB Rate')
                    ->numeric(
                        decimalPlaces: 0,
                        decimalSeparator: '.',
                        thousandsSeparator: ',',
                    )
                    ->formatStateUsing(function ($state) {
                        // Remove trailing zeros and unnecessary decimals
                        $formatted = rtrim(rtrim(number_format($state, 6, '.', ''), '0'), '.');
                        return '1 USD = ' . $formatted . ' RUB';
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
