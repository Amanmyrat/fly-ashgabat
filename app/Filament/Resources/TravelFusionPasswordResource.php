<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TravelFusionPasswordResource\Pages;
use App\Models\TravelFusionPassword;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TravelFusionPasswordResource extends Resource
{
    protected static ?string $model = TravelFusionPassword::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'TravelFusion';

    protected static ?string $modelLabel = 'Password';

    protected static ?string $pluralModelLabel = 'Passwords';

    protected static ?string $slug = 'travel-fusion-passwords';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Passwords';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Passwords';
    }

    public static function getModelLabel(): string
    {
        return 'Password';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('username')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('login_id')
                    ->label('Login ID')
                    ->helperText('This is used for API authentication. Only change if you know what you are doing.')
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('changed_at')
                    ->required(),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->searchable(),
                Tables\Columns\TextColumn::make('login_id')
                    ->label('Login ID')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('changed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable()
                    ->state(function (TravelFusionPassword $record): string {
                        return $record->expires_at->format('Y-m-d H:i:s');
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
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
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('expires_at')
                    ->label(fn (TravelFusionPassword $record): string => $record->expires_at->format('Y-m-d H:i:s'))
                    ->color(function (TravelFusionPassword $record): string {
                        if ($record->isExpired()) {
                            return 'danger';
                        }
                        if ($record->isExpiringSoon()) {
                            return 'warning';
                        }
                        return 'success';
                    }),
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
            'index' => Pages\ListTravelFusionPassword::route('/'),
            'create' => Pages\CreateTravelFusionPassword::route('/create'),
            'edit' => Pages\EditTravelFusionPassword::route('/{record}/edit'),
        ];
    }
}
