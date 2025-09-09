<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CharterFlightResource\Pages;
use App\Models\CharterFlight;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CharterFlightResource extends Resource
{
    protected static ?string $model = CharterFlight::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'Charter Flights';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('city_from_id')
                    ->label('Departure City')
                    ->options(City::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('city_to_id')
                    ->label('Destination City')
                    ->options(City::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Forms\Components\DateTimePicker::make('departure_datetime')
                    ->label('Departure Date & Time')
                    ->required(),
                Forms\Components\TextInput::make('price')
                    ->label('Price')
                    ->numeric()
                    ->prefix('$')
                    ->step(0.01)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cityFrom.name')
                    ->label('From')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cityTo.name')
                    ->label('To')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('departure_datetime')
                    ->label('Departure')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('city_from_id')
                    ->label('Departure City')
                    ->options(City::all()->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('city_to_id')
                    ->label('Destination City')
                    ->options(City::all()->pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('departure_datetime', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCharterFlights::route('/'),
            'create' => Pages\CreateCharterFlight::route('/create'),
            'edit' => Pages\EditCharterFlight::route('/{record}/edit'),
        ];
    }
} 