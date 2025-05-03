<?php

namespace App\Filament\Resources\VisaResource\Pages;

use App\Filament\Resources\VisaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateVisa extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = VisaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }
}
