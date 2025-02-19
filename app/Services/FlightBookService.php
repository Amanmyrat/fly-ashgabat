<?php

namespace App\Services;

use App\Enum\BookingStatus;
use App\Models\FlightBooking;
use App\Models\User;
use App\Services\TravelFusion\Requests\ProcessTermsRequestBuilder;
use App\Services\TravelFusion\TravelFusionService;
use Exception;
use Illuminate\Http\Client\ConnectionException;

class FlightBookService
{
    public function __construct(protected TravelFusionService $travelFusionService)
    {
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function processDetails(array $validatedData, ?User $user): array
    {
        // TODO add baggage and luggage options to request
        $processTermsRequest = (new ProcessTermsRequestBuilder($validatedData))->build();
        $processTermsResponse = $this->travelFusionService->sendRequest($processTermsRequest, 'processTerms');

        if (!isset($processTermsResponse['ProcessTerms']['Router']['GroupList']['Group'])) {
            return [
                'success' => false,
                'message' => 'No result(ProcessTerms) found',
                'data' => $processTermsResponse
            ];
        }

        $bookingReference = $processTermsResponse['ProcessTerms']['TFBookingReference'];
        $fullPrice = $processTermsResponse['ProcessTerms']['Router']['GroupList']['Group']['Price'];

        if ($validatedData['payment_type'] === 'balance' && (!$user || $user->balance < $fullPrice['Amount'])) {
            return ['success' => false, 'message' => 'Insufficient balance.', 'balance' => $user->balance, 'price' => $fullPrice['Amount']];
        }

        // TODO save some date for booking reference pay time (15min)
        $bookingData = [
            'user_id' => $user?->id ?? null,
            'booking_reference' => $bookingReference,
            'origin' => $processTermsResponse['ProcessTerms']['Router']['RequestedLocations']['Origin'],
            'destination' => $processTermsResponse['ProcessTerms']['Router']['RequestedLocations']['Destination'],
            'outward' => $processTermsResponse['ProcessTerms']['Router']['GroupList']['Group']['OutwardList']['Outward'],
            'return' => $processTermsResponse['ProcessTerms']['Router']['GroupList']['Group']['ReturnList']['Return'] ?? null,
            'price' => $fullPrice,
            'features' => [
                'HoldBag' => false,
                'SmallCabinBag' => false,
                'LargeCabinBag' => false,
                'FlightChange' => false,
                'Cancellation' => false,
            ],
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
