<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\FlightBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBooking extends ViewRecord
{
    protected static string $resource = FlightBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
