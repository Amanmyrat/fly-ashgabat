<?php

namespace App\Filament\Resources\CharterFlightResource\Pages;

use App\Filament\Resources\CharterFlightResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCharterFlights extends ListRecords
{
    protected static string $resource = CharterFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
} 