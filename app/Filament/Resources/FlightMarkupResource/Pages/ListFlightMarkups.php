<?php

namespace App\Filament\Resources\FlightMarkupResource\Pages;

use App\Filament\Resources\FlightMarkupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFlightMarkups extends ListRecords
{
    protected static string $resource = FlightMarkupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
