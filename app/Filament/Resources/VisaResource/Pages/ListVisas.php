<?php

namespace App\Filament\Resources\VisaResource\Pages;

use App\Filament\Resources\VisaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVisas extends ListRecords
{
    use ListRecords\Concerns\Translatable;

    protected static string $resource = VisaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
