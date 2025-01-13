<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TourResource\Pages;
use App\Models\Tour;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\Concerns\Translatable;

class TourResource extends Resource
{
    use Translatable;

    protected static ?string $model = Tour::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\TextInput::make('location')
                    ->required(),
                Forms\Components\TextInput::make('days')
                    ->numeric()
                    ->required(),
                Forms\Components\Repeater::make('included')
                    ->schema([
                        Forms\Components\TextInput::make('item')->label('Included Item'),
                    ])
                    ->columns(1)
                    ->required(),
                Forms\Components\Repeater::make('not_included')
                    ->schema([
                        Forms\Components\TextInput::make('item')->label('Not Included Item'),
                    ])
                    ->columns(1)
                    ->required(),
                Forms\Components\FileUpload::make('main_image')
                    ->image()
                    ->required()
                    ->directory('destination/main-images'),
                Forms\Components\FileUpload::make('background_image')
                    ->image()
                    ->required()
                    ->directory('destination/background-images'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID'),
                Tables\Columns\ImageColumn::make('main_image')->label('Main Image'),
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('location')->label('Location')->sortable(),
                Tables\Columns\TextColumn::make('days'),
            ])
            ->filters([
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
            ->reorderable('order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTours::route('/'),
            'create' => Pages\CreateTour::route('/create'),
            'edit' => Pages\EditTour::route('/{record}/edit'),
        ];
    }
}
