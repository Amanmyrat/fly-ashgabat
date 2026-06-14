<?php

namespace App\Observers;

use App\Models\CurrencyRate;
use App\Services\FlightMarkupService;

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
        try {
            app(FlightMarkupService::class)->clearCache();
        } catch (\Exception $e) {
            logger()->error('Failed to clear pricing cache: ' . $e->getMessage());
        }
    }
}
