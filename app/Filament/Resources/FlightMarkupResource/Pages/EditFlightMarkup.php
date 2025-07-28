<?php

namespace App\Filament\Resources\FlightMarkupResource\Pages;

use App\Filament\Resources\FlightMarkupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFlightMarkup extends EditRecord
{
    protected static string $resource = FlightMarkupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
