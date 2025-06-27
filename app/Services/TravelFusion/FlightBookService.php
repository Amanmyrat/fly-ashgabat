<?php

namespace App\Services\TravelFusion;

use App\Enum\BookingStatus;
use App\Enum\PaymentType;
use App\Models\FlightBooking;
use App\Models\User;
use App\Services\IpGeolocationService;
use App\Services\TravelFusion\Requests\ProcessTermsRequestBuilder;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Str;

class FlightBookService
{
    public function __construct(
        protected TravelFusionService  $travelFusionService,
        protected IpGeolocationService $ipGeolocationService
    )
    {
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function processTerms(array $validatedData, ?User $user): array
    {
        $processTermsRequest = (new ProcessTermsRequestBuilder($validatedData, $this->ipGeolocationService))->build();
        $processTermsResponse = $this->travelFusionService->sendRequest($processTermsRequest, 'processTerms');

        if (!isset($processTermsResponse['ProcessTerms']['Router']['GroupList']['Group'])) {
            return [
                'success' => false,
                'message' => 'No result(ProcessTerms) found',
                'data' => $processTermsResponse
            ];
        }

        $bookingReference = $processTermsResponse['ProcessTerms']['TFBookingReference'];
        $priceData = $processTermsResponse['ProcessTerms']['Router']['GroupList']['Group']['Price'];
        $fullPrice = [
            'Amount' => $priceData['Amount'],
            'Currency' => $priceData['Currency']
        ];

        if ($validatedData['payment_type'] === PaymentType::BALANCE->value && (!$user || $user->balance < $fullPrice['Amount'])) {
            return ['success' => false, 'message' => 'Insufficient balance.', 'balance' => $user->balance, 'price' => $fullPrice['Amount']];
        }

        // TODO save some date for booking reference pay time (15min)
        $outward = $processTermsResponse['ProcessTerms']['Router']['GroupList']['Group']['OutwardList']['Outward'];
        $return = $processTermsResponse['ProcessTerms']['Router']['GroupList']['Group']['ReturnList']['Return'] ?? null;

        $outwardCacheKey = $validatedData['routing_id'] . '_' . $validatedData['outward_id'];
        $outwardFeatures = Cache::get('process_' . $outwardCacheKey, []);

        // Return (only if return_id is present)
        $returnFeatures = null;
        if (!empty($validatedData['return_id'])) {
            $returnCacheKey = $validatedData['routing_id'] . '_' . $validatedData['return_id'];
            $returnFeatures = Cache::get('process_' . $returnCacheKey, []);
        }

        $options = Cache::get('options_' . $validatedData['routing_id'], []);

        // Only process options if they exist in the validated data
        if (!empty($validatedData['options'])) {
            foreach ($validatedData['options'] as $optionKey => $selectedKey) {
            $isOutward = Str::startsWith($optionKey, 'Outward');
            $isReturn = Str::startsWith($optionKey, 'Return');

            $cleanKey = $optionKey;
            if ($isOutward) {
                $cleanKey = Str::replaceFirst('Outward', '', $cleanKey);
            } elseif ($isReturn) {
                $cleanKey = Str::replaceFirst('Return', '', $cleanKey);
            }

            $featureKey = match ($cleanKey) {
                'HandLuggageOptions' => 'CabinBag',
                'LuggageOptions' => 'HoldBag',
                default => $cleanKey,
            };

            // Find the option by name in the new structure
            $optionData = collect($options)->first(function ($option) use ($optionKey, $cleanKey) {
                return isset($option['name']) && ($option['name'] === $optionKey || $option['name'] === $cleanKey);
            });

            if (!$optionData || !isset($optionData['options'])) {
                continue;
            }

            $subOptions = $optionData['options'];

            $selectedOption = collect($subOptions)
                ->first(fn($opt) => isset($opt['key']) && (string)$opt['key'] === (string)$selectedKey);

            if (!$selectedOption) {
                continue;
            }
            $formattedValue = $selectedOption['value'];

            // Create the formatted string using regex
            $formattedValue = preg_replace(
                ['/bags/', '/\stotal/', '/\s-\s[^-]*$/', '/\s-\s/', '/\s-\s$/'],
                ['x', '', '', ' ', ''],
                $formattedValue
            );

            $formatted = [
                'Bundled' => true,
                'Value' => trim($formattedValue) ?? '',
            ];

            if ($isOutward) {
                $outwardFeatures[$featureKey] = $formatted;
            } elseif ($isReturn) {
                if ($returnFeatures !== null) {
                    $returnFeatures[$featureKey] = $formatted;
                }
            } else {
                $outwardFeatures[$featureKey] = $formatted;
                if ($returnFeatures !== null) {
                    $returnFeatures[$featureKey] = $formatted;
                }
            }
        }
        }

        $outward['Features'] = $outwardFeatures;
        if ($return) {
            $return['Features'] = $returnFeatures;
        }

        $bookingData = [
            'user_id' => $user?->id ?? null,
            'booking_reference' => $bookingReference,
            'flight_type' => 'TFusion',
            'origin' => $processTermsResponse['ProcessTerms']['Router']['RequestedLocations']['Origin'],
            'destination' => $processTermsResponse['ProcessTerms']['Router']['RequestedLocations']['Destination'],
            'outward' => $outward,
            'return' => $return,
            'price' => $fullPrice,
            'payment_type' => $validatedData['payment_type'],
            'status' => BookingStatus::PENDING->value,
        ];

        $booking = FlightBooking::create($bookingData);

        if (!$booking) {
            throw new \Exception('Failed to create booking');
        }

        if (!empty($validatedData['contact_details'])) {
            $booking->contactDetail()->create($validatedData['contact_details']);
        }

        if (!empty($validatedData['travellers'])) {
            $booking->travellers()->createMany($validatedData['travellers']);
        }

        return [
            'success' => true,
            'booking' => $booking,
        ];
    }

    /**
     * Get booking details
     */
    public function getBookingDetails(string $bookId): array
    {
        $booking = FlightBooking::with('tickets')->where('booking_reference', $bookId)->first();
        return $booking ? ['success' => true, 'data' => $booking] : ['success' => false, 'message' => 'Booking not found'];
    }
}
