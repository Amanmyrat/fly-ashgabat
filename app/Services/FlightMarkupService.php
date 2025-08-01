<?php

namespace App\Services;

use App\Enum\FlightSupplier;
use App\Models\FlightMarkup;
use App\Models\CurrencyRate;
use Illuminate\Support\Facades\Cache;

class FlightMarkupService
{
    /**
     * Apply markup to a price based on supplier and airline
     * This is the main method used externally
     *
     * @param float $originalAmount
     * @param string $currency
     * @param FlightSupplier $supplier
     * @param string|null $airlineCode
     * @return array
     */
    public function applyMarkup(
        float $originalAmount,
        string $currency,
        FlightSupplier $supplier,
        ?string $airlineCode = null
    ): array {
        $convertedAmount = $originalAmount;
        $finalCurrency = $currency;
        $conversionRate = null;
        $originalCurrency = $currency;

        if ($supplier === FlightSupplier::NEMO && strtoupper($currency) === 'RUB') {
            $usdAmount = $this->convertRubToUsd($originalAmount);
            if ($usdAmount !== null) {
                $convertedAmount = $usdAmount;
                $finalCurrency = 'USD';
                $conversionRate = CurrencyRate::getLatestRubToUsdRate();
            }
        }

        $markupPercentage = $this->getMarkupPercentage($supplier, $airlineCode);
        $markupAmount = $convertedAmount * ($markupPercentage / 100);
        $finalAmount = $convertedAmount + $markupAmount;

        $result = [
            'Amount' => round($finalAmount, 2),
            'Currency' => $finalCurrency,
            'PriceWithoutMarkup' => round($convertedAmount, 2),
            'MarkupPercentage' => $markupPercentage,
            'MarkupAmount' => round($markupAmount, 2),
        ];

        // Add conversion details if currency was converted
        if ($conversionRate !== null) {
            $result['OriginalAmount'] = round($originalAmount, 2);
            $result['OriginalCurrency'] = $originalCurrency;
            $result['ConversionRate'] = $conversionRate;
            $result['ConvertedFrom'] = "{$originalAmount} {$originalCurrency}";
        }

        return $result;
    }

    /**
     * Calculate reverse markup (get original price from marked up price)
     * Public method that might be needed for calculations
     *
     * @param float $markedUpAmount
     * @param string $currency
     * @param FlightSupplier $supplier
     * @param string|null $airlineCode
     * @return array
     */
    public function reverseMarkup(
        float $markedUpAmount,
        string $currency,
        FlightSupplier $supplier,
        ?string $airlineCode = null
    ): array {
        $markupPercentage = $this->getMarkupPercentage($supplier, $airlineCode);
        $originalAmount = $markedUpAmount / (1 + ($markupPercentage / 100));
        $markupAmount = $markedUpAmount - $originalAmount;

        $result = [
            'Amount' => round($markedUpAmount, 2),
            'Currency' => $currency,
            'PriceWithoutMarkup' => round($originalAmount, 2),
            'MarkupPercentage' => $markupPercentage,
            'MarkupAmount' => round($markupAmount, 2),
        ];

        // If this was originally converted from RUB for Nemo supplier
        if ($supplier === FlightSupplier::NEMO && strtoupper($currency) === 'USD') {
            $conversionRate = CurrencyRate::getLatestRubToUsdRate();
            if ($conversionRate !== null && $conversionRate > 0) {
                $rubOriginalAmount = $originalAmount / $conversionRate;
                $result['PossibleOriginalRubAmount'] = round($rubOriginalAmount, 2);
                $result['ConversionRate'] = $conversionRate;
            }
        }

        return $result;
    }

    /**
     * Get the applicable markup percentage for a supplier and airline
     * Private - only used internally
     *
     * @param FlightSupplier $supplier
     * @param string|null $airlineCode
     * @return float
     */
    private function getMarkupPercentage(FlightSupplier $supplier, ?string $airlineCode = null): float
    {
        $cacheKey = "flight_markup_{$supplier->value}_" . ($airlineCode ?? 'all');

        return Cache::tags(['flight_markups'])->remember($cacheKey, 86400, function () use ($supplier, $airlineCode) {
            // First try to find a specific markup for the airline
            if ($airlineCode) {
                $specificMarkup = FlightMarkup::active()
                    ->forSupplier($supplier)
                    ->forAirline($airlineCode)
                    ->first();

                if ($specificMarkup) {
                    return (float) $specificMarkup->markup_percentage;
                }
            }

            // If no specific airline markup found, try to find a general markup for the supplier
            $generalMarkup = FlightMarkup::active()
                ->forSupplier($supplier)
                ->forAirline(null)
                ->first();

            if ($generalMarkup) {
                return (float) $generalMarkup->markup_percentage;
            }

            // If no markup found, return 0%
            return 0.0;
        });
    }

    /**
     * Convert RUB amount to USD using the latest exchange rate
     *
     * @param float $rubAmount
     * @return float|null
     */
    private function convertRubToUsd(float $rubAmount): ?float
    {
        $cacheKey = 'latest_rub_usd_rate';

        $rate = Cache::tags(['currency_rates'])->remember($cacheKey, 3600, function () {
            return CurrencyRate::getLatestRubToUsdRate();
        });

        return $rate ? CurrencyRate::convertRubToUsd($rubAmount) : null;
    }

    /**
     * Clear all cached markup percentages and currency rates
     * Called when markup data or currency rates are changed
     *
     * @return void
     */
    public function clearCache(): void
    {
        // Clear all cache entries tagged with 'flight_markups'
        Cache::tags(['flight_markups'])->flush();

        // Clear currency rate cache as well
        Cache::tags(['currency_rates'])->flush();
    }

    /**
     * Clear only currency rate cache
     * Called specifically when currency rates are updated
     *
     * @return void
     */
    public function clearCurrencyRateCache(): void
    {
        Cache::tags(['currency_rates'])->flush();
    }
}
