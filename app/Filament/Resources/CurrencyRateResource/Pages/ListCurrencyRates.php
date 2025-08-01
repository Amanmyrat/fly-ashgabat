<?php

namespace App\Filament\Resources\CurrencyRateResource\Pages;

use App\Filament\Resources\CurrencyRateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCurrencyRates extends ListRecords
{
    protected static string $resource = CurrencyRateResource::class;
    
    protected ?string $heading = 'USD to RUB Exchange Rates';
    
    protected ?string $subheading = 'Manage currency conversion rates for USD to RUB';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add New Rate'),
        ];
    }
}
