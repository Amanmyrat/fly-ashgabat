<?php

namespace App\Services;

use App\Enum\FlightSupplier;
use App\Models\FlightMarkup;
use Illuminate\Support\Facades\Cache;

/**
 * FlightMarkupService - Service for applying markup to flight prices
 * 
 * Cache Strategy:
 * - Markup percentages are cached for 24 hours for performance
 * - Cache is automatically cleared when markup data changes (create/update/delete)
 * - This allows aggressive caching with immediate updates when needed
 * 
 * Usage Examples:
 * 
 * // Basic usage:
 * $markupService = new FlightMarkupService();
 * $result = $markupService->applyMarkup(100.00, 'USD', FlightSupplier::NEMO, 'AA');
 * 
 * // In your Nemo FlightFilterService:
 * foreach ($flightsData as &$flight) {
 *     $originalSum = $flight->TotalSum;
 *     $airlineCode = $flight->Segments->Segment[0]->MarketingAirline ?? null;
 *     
 *     $priceWithMarkup = $markupService->applyMarkup(
 *         $originalSum, 'USD', FlightSupplier::NEMO, $airlineCode
 *     );
 *     
 *     $flight->TotalSum = $priceWithMarkup['Amount'];
 *     $flight->OriginalPrice = $priceWithMarkup['PriceWithoutMarkup'];
 * }
 * 
 * // In your XMLAgency FlightSearchService:
 * $originalAmount = floatval($flight['TotalPrice']['value']);
 * $priceWithMarkup = $markupService->applyMarkup(
 *     $originalAmount, 'USD', FlightSupplier::XMLAGENCY, $airlineCode
 * );
 * 
 * $transformedFlight['TotalSum']['Amount'] = $priceWithMarkup['Amount'];
 */
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
        $markupPercentage = $this->getMarkupPercentage($supplier, $airlineCode);

        $markupAmount = $originalAmount * ($markupPercentage / 100);
        $finalAmount = $originalAmount + $markupAmount;

        return [
            'Amount' => round($finalAmount, 2),
            'Currency' => $currency,
            'PriceWithoutMarkup' => round($originalAmount, 2),
            'MarkupPercentage' => $markupPercentage,
            'MarkupAmount' => round($markupAmount, 2),
        ];
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

        return [
            'Amount' => round($markedUpAmount, 2),
            'Currency' => $currency,
            'PriceWithoutMarkup' => round($originalAmount, 2),
            'MarkupPercentage' => $markupPercentage,
            'MarkupAmount' => round($markupAmount, 2),
        ];
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
     * Clear all cached markup percentages
     * Called when markup data is changed
     *
     * @return void
     */
    public function clearCache(): void
    {
        // Clear all cache entries tagged with 'flight_markups'
        Cache::tags(['flight_markups'])->flush();
    }
}
