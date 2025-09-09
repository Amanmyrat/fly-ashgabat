<?php

namespace App\Filament\Resources\TravelFusionPasswordResource\Pages;

use App\Filament\Resources\TravelFusionPasswordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTravelFusionPassword extends ListRecords
{
    protected static string $resource = TravelFusionPasswordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
