<?php

namespace App\Filament\Resources\CurrencyRateResource\Pages;

use App\Filament\Resources\CurrencyRateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCurrencyRate extends EditRecord
{
    protected static string $resource = CurrencyRateResource::class;
    
    protected ?string $heading = 'Edit USD to RUB Rate';
    
    protected ?string $subheading = 'Update the exchange rate for converting US Dollars to Russian Rubles';

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Delete Rate'),
        ];
    }
}
