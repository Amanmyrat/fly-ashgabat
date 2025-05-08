<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TravelFusionPasswordChangeResource\Pages;
use App\Models\TravelFusionPasswordChange;
use App\Services\TravelFusion\TravelFusionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TravelFusionPasswordChangeResource extends Resource
{
    protected static ?string $model = TravelFusionPasswordChange::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'TravelFusion';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('username')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Password must be 8-20 characters, contain uppercase, lowercase, number and special character (?!@#$%^+-_=)'),
                Forms\Components\DateTimePicker::make('changed_at')
                    ->required()
                    ->default(now()),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->required()
                    ->default(now()->addDays(90)),
                Forms\Components\Toggle::make('is_active')
                    ->required()
                    ->default(true)
                    ->helperText('Setting this to active will change the password in TravelFusion and deactivate the current active password.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->searchable(),
                Tables\Columns\TextColumn::make('changed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('days_remaining')
                    ->label('Days Remaining')
                    ->state(function (TravelFusionPasswordChange $record): string {
                        if (!$record->is_active) {
                            return 'Inactive';
                        }
                        
                        $days = (int) now()->diffInDays($record->expires_at, false);
                        
                        if ($days < 0) {
                            return 'Expired';
                        }
                        
                        if ($days <= 15) {
                            return "{$days} days (Expiring Soon)";
                        }
                        
                        return "{$days} days";
                    })
                    ->color(function (TravelFusionPasswordChange $record): string {
                        if (!$record->is_active) {
                            return 'gray';
                        }
                        
                        $days = now()->diffInDays($record->expires_at, false);
                        
                        if ($days < 0) {
                            return 'danger';
                        }
                        
                        if ($days <= 15) {
                            return 'warning';
                        }
                        
                        return 'success';
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
                Tables\Filters\SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generate_password')
                    ->label('Generate Password')
                    ->action(function (TravelFusionPasswordChange $record) {
                        $password = static::generatePassword();
                        $record->update(['password' => $password]);
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
            'index' => Pages\ListTravelFusionPasswordChanges::route('/'),
            'create' => Pages\CreateTravelFusionPasswordChange::route('/create'),
            'edit' => Pages\EditTravelFusionPasswordChange::route('/{record}/edit'),
        ];
    }

    public static function generatePassword(): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '?!@#$%^+-_=';

        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        // Fill the rest with random characters
        $all = $uppercase . $lowercase . $numbers . $special;
        $length = random_int(8, 20) - 4; // Subtract 4 because we already added 4 characters
        $password .= Str::random($length);

        // Shuffle the password
        return str_shuffle($password);
    }
} 