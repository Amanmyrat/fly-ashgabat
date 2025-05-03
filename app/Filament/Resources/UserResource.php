<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    /**
     * Only allow editing. This disables the creation of new users.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            TextInput::make('firstname')
                ->label('First Name')
                ->required()
                ->maxLength(255),

            TextInput::make('lastname')
                ->label('Last Name')
                ->required()
                ->maxLength(255),

            TextInput::make('company')
                ->label('Company')
                ->maxLength(255),

            TextInput::make('balance')
                ->label('Balance')
                ->numeric()
                ->minValue(0),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            TextColumn::make('email')
                ->label('Email')
                ->sortable()
                ->searchable(),

            TextColumn::make('firstname')
                ->label('First Name')
                ->sortable()
                ->searchable(),

            TextColumn::make('lastname')
                ->label('Last Name')
                ->sortable()
                ->searchable(),

            TextColumn::make('company')
                ->label('Company')
                ->sortable()
                ->searchable(),

            TextColumn::make('balance')
                ->label('Balance')
                ->sortable(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'edit'  => EditUser::route('/{record}/edit'),
        ];
    }
}
