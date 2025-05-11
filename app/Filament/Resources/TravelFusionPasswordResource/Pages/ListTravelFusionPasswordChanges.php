<?php

namespace App\Filament\Resources\TravelFusionPasswordChangeResource\Pages;

use App\Filament\Resources\TravelFusionPasswordChangeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTravelFusionPasswordChanges extends ListRecords
{
    protected static string $resource = TravelFusionPasswordChangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
} 