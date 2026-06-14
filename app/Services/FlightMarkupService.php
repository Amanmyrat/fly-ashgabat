<?php

namespace App\Services;

use App\Enum\FlightSupplier;
use App\Models\CurrencyRate;
use App\Models\FlightMarkup;
use Illuminate\Support\Facades\Cache;

class FlightMarkupService
{
    private const CACHE_TTL = 86400;
    private const CACHE_TAGS_MARKUP = ['flight_markups'];
    private const CACHE_TAGS_CURRENCY = ['currency_rates'];

    private const USD_CONVERTIBLE_SUPPLIERS = [
        FlightSupplier::NEMO,
        FlightSupplier::MYAGENT,
    ];

    private const USD_CONVERTIBLE_CURRENCIES = [
        'RUB',
        'KZT',
        'UZS',
    ];

    public function applyMarkup(
        float $originalAmount,
        string $currency,
        FlightSupplier $supplier,
        ?string $airlineCode = null,
        ?string $departureCode = null,
        ?string $arrivalCode = null
    ): array {
        $currency = strtoupper($currency);
        $airlineCode = $airlineCode ? strtoupper($airlineCode) : null;
        $departureCode = $departureCode ? strtoupper($departureCode) : null;
        $arrivalCode = $arrivalCode ? strtoupper($arrivalCode) : null;

        $convertedAmount = $originalAmount;
        $finalCurrency = $currency;
        $conversionRate = null;

        if ($this->shouldConvertToUsd($supplier, $currency)) {
            $rate = CurrencyRate::getLatestCurrencyToUsdRate($currency);

            if ($rate && $rate > 0) {
                $convertedAmount = round($originalAmount * $rate, 2);
                $finalCurrency = 'USD';
                $conversionRate = $rate;
            }
        }

        $markupPercentage = $this->getMarkupPercentage(
            $supplier,
            $airlineCode,
            $departureCode,
            $arrivalCode
        );

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

    private function shouldConvertToUsd(FlightSupplier $supplier, string $currency): bool
    {
        return in_array($supplier, self::USD_CONVERTIBLE_SUPPLIERS, true)
            && in_array($currency, self::USD_CONVERTIBLE_CURRENCIES, true);
    }

    private function getMarkupPercentage(
        FlightSupplier $supplier,
        ?string $airlineCode = null,
        ?string $departureCode = null,
        ?string $arrivalCode = null
    ): float {
        $cacheKey = implode('_', [
            'flight_markup',
            $supplier->value,
            $airlineCode ?: 'all_airlines',
            $departureCode ?: 'all_departures',
            $arrivalCode ?: 'all_arrivals',
        ]);

        return Cache::tags(self::CACHE_TAGS_MARKUP)->remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($supplier, $airlineCode, $departureCode, $arrivalCode) {
                return $this->resolveMarkupPercentage(
                    $supplier,
                    $airlineCode,
                    $departureCode,
                    $arrivalCode
                );
            }
        );
    }

    private function resolveMarkupPercentage(
        FlightSupplier $supplier,
        ?string $airlineCode = null,
        ?string $departureCode = null,
        ?string $arrivalCode = null
    ): float {
        // 1. Supplier + direction + airline
        if ($departureCode && $arrivalCode && $airlineCode) {
            $markup = FlightMarkup::active()
                ->forSupplier($supplier)
                ->forAirline($airlineCode)
                ->forRoute($departureCode, $arrivalCode)
                ->first();

            if ($markup) {
                return (float) $markup->markup_percentage;
            }
        }

        // 2. Supplier + direction only
        if ($departureCode && $arrivalCode) {
            $markup = FlightMarkup::active()
                ->forSupplier($supplier)
                ->forAirline(null)
                ->forRoute($departureCode, $arrivalCode)
                ->first();

            if ($markup) {
                return (float) $markup->markup_percentage;
            }
        }

        // 3. Supplier + airline only
        if ($airlineCode) {
            $markup = FlightMarkup::active()
                ->forSupplier($supplier)
                ->forAirline($airlineCode)
                ->defaultRoute()
                ->first();

            if ($markup) {
                return (float) $markup->markup_percentage;
            }
        }

        // 4. Supplier default
        $markup = FlightMarkup::active()
            ->forSupplier($supplier)
            ->forAirline(null)
            ->defaultRoute()
            ->first();

        return $markup ? (float) $markup->markup_percentage : 0.0;
    }

    public function clearCache(): void
    {
        Cache::tags(self::CACHE_TAGS_MARKUP)->flush();
        Cache::tags(self::CACHE_TAGS_CURRENCY)->flush();

        app(FlightSearchCacheService::class)->clear();
    }

    public function clearCurrencyRateCache(): void
    {
        Cache::tags(self::CACHE_TAGS_CURRENCY)->flush();
    }
}
