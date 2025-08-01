<?php

namespace App\Filament\Resources\CurrencyRateResource\Pages;

use App\Filament\Resources\CurrencyRateResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCurrencyRate extends CreateRecord
{
    protected static string $resource = CurrencyRateResource::class;
    
    protected ?string $heading = 'Add New USD to RUB Rate';
    
    protected ?string $subheading = 'Set the exchange rate for converting US Dollars to Russian Rubles';
}
