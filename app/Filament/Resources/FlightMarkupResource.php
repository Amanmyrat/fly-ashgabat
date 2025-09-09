<?php

namespace App\Filament\Resources;

use App\Enum\FlightSupplier;
use App\Filament\Resources\FlightMarkupResource\Pages;
use App\Filament\Resources\FlightMarkupResource\RelationManagers;
use App\Models\FlightMarkup;
use App\Services\FlightMarkupService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FlightMarkupResource extends Resource
{
    protected static ?string $model = FlightMarkup::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    
    protected static ?string $navigationLabel = 'Flight Markups';
    
    protected static ?string $navigationGroup = 'Flight Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('supplier')
                    ->label('Flight Supplier')
                    ->options(FlightSupplier::class)
                    ->required()
                    ->native(false),
                    
                Forms\Components\TextInput::make('airline_code')
                    ->label('Airline Code')
                    ->placeholder('e.g., AA, BA, EK (leave empty for all airlines)')
                    ->maxLength(2)
                    ->helperText('2-digit airline code. Leave empty to apply to all airlines for this supplier.'),
                    
                Forms\Components\TextInput::make('markup_percentage')
                    ->label('Markup Percentage')
                    ->numeric()
                    ->suffix('%')
                    ->step(0.01)
                    ->required()
                    ->helperText('The percentage markup to apply to the base price'),
                    
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Whether this markup rule is currently active'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('supplier')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('airline_code')
                    ->label('Airline Code')
                    ->placeholder('All Airlines')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('markup_percentage')
                    ->label('Markup %')
                    ->suffix('%')
                    ->sortable()
                    ->alignEnd(),
                    
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier')
                    ->options(FlightSupplier::class),
                    
                Tables\Filters\Filter::make('is_active')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->label('Active Only'),
                    
                Tables\Filters\Filter::make('has_airline_code')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('airline_code'))
                    ->label('Specific Airlines Only'),
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
            ->headerActions([
                Tables\Actions\Action::make('clear_cache')
                    ->label('Clear Cache')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(function () {
                        app(FlightMarkupService::class)->clearCache();
                        Notification::make()
                            ->title('Cache cleared successfully')
                            ->success()
                            ->send();
                    })
                    ->tooltip('Manually clear markup cache (automatically cleared when data changes)'),
            ])
            ->defaultSort('supplier');
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
            'index' => Pages\ListFlightMarkups::route('/'),
            'create' => Pages\CreateFlightMarkup::route('/create'),
            'edit' => Pages\EditFlightMarkup::route('/{record}/edit'),
        ];
    }
}
