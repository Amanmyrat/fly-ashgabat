<?php

namespace App\Services;

use App\Enum\FlightSupplier;
use App\Models\FlightMarkup;
use App\Models\CurrencyRate;
use Illuminate\Support\Facades\Cache;

class FlightMarkupService
{
    private const CACHE_TTL = 86400; // 24 hours
    private const CACHE_TAGS_MARKUP = ['flight_markups'];
    private const CACHE_TAGS_CURRENCY = ['currency_rates'];

    public function applyMarkup(
        float $originalAmount,
        string $currency,
        FlightSupplier $supplier,
        ?string $airlineCode = null
    ): array {
        $convertedAmount = $originalAmount;
        $finalCurrency = $currency;
        $conversionRate = null;

        // Handle RUB to USD conversion for Nemo supplier
        if ($supplier === FlightSupplier::NEMO && strtoupper($currency) === 'RUB') {
            $rate = CurrencyRate::getLatestRubToUsdRate();
            if ($rate && $rate > 0) {
                $convertedAmount = round($originalAmount * $rate, 2);
                $finalCurrency = 'USD';
                $conversionRate = $rate;
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

        if ($conversionRate !== null) {
            $result['OriginalAmount'] = round($originalAmount, 2);
            $result['OriginalCurrency'] = $currency;
            $result['ConversionRate'] = $conversionRate;
            $result['ConvertedFrom'] = "{$originalAmount} {$currency}";
        }

        return $result;
    }

    private function getMarkupPercentage(FlightSupplier $supplier, ?string $airlineCode = null): float
    {
        $cacheKey = "flight_markup_{$supplier->value}_" . ($airlineCode ?? 'all');

        return Cache::tags(self::CACHE_TAGS_MARKUP)->remember($cacheKey, self::CACHE_TTL, function () use ($supplier, $airlineCode) {
            if ($airlineCode) {
                $markup = FlightMarkup::active()->forSupplier($supplier)->forAirline($airlineCode)->first();
                if ($markup) {
                    return (float) $markup->markup_percentage;
                }
            }

            $markup = FlightMarkup::active()->forSupplier($supplier)->forAirline(null)->first();
            return $markup ? (float) $markup->markup_percentage : 0.0;
        });
    }

    public function clearCache(): void
    {
        Cache::tags(self::CACHE_TAGS_MARKUP)->flush();
        Cache::tags(self::CACHE_TAGS_CURRENCY)->flush();
    }

    public function clearCurrencyRateCache(): void
    {
        Cache::tags(self::CACHE_TAGS_CURRENCY)->flush();
    }
}
