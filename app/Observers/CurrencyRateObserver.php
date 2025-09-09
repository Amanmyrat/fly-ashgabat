<?php

namespace App\Observers;

use App\Models\CurrencyRate;
use App\Services\FlightMarkupService;
use Illuminate\Support\Facades\Cache;

class CurrencyRateObserver
{
    /**
     * Handle the CurrencyRate "created" event.
     */
    public function created(CurrencyRate $currencyRate): void
    {
        $this->clearCurrencyCache();
    }

    /**
     * Handle the CurrencyRate "updated" event.
     */
    public function updated(CurrencyRate $currencyRate): void
    {
        $this->clearCurrencyCache();
    }

    /**
     * Handle the CurrencyRate "deleted" event.
     */
    public function deleted(CurrencyRate $currencyRate): void
    {
        $this->clearCurrencyCache();
    }

    /**
     * Handle the CurrencyRate "restored" event.
     */
    public function restored(CurrencyRate $currencyRate): void
    {
        $this->clearCurrencyCache();
    }

    /**
     * Clear currency rate related caches
     */
    private function clearCurrencyCache(): void
    {
        // Clear currency rate cache
        Cache::tags(['currency_rates'])->flush();
        
        // Clear the FlightMarkupService cache as well since it depends on currency rates
        try {
            $markupService = app(FlightMarkupService::class);
            $markupService->clearCurrencyRateCache();
        } catch (\Exception $e) {
            // Log error but don't fail the operation
            logger()->error('Failed to clear FlightMarkupService cache: ' . $e->getMessage());
        }
    }
}
