<?php

namespace App\Services\XMLAgency;

use App\Enum\BookingStatus;
use App\Enum\PaymentType;
use App\Models\FlightBooking;
use App\Models\User;

use App\Http\Requests\XMLAgency\AeroBookRequestBuilder;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FlightBookService
{
    public function __construct(
        protected XMLAgencyService $xmlAgencyService
    ) {
    }

    /**
     * Process XMLAgency booking
     *
     * @throws ConnectionException
     * @throws Exception
     */
    public function processBooking(array $validatedData, ?User $user): array
    {
//        // For XMLAgency, we don't pre-calculate price - it comes from the booking response
//        // Set a placeholder price that will be updated after booking
//        $fullPrice = [
//            'Amount' => 0, // Will be set from booking response
//            'Currency' => 'EUR'
//        ];
//
//        // Check balance if payment type is balance
//        if ($validatedData['payment_type'] === PaymentType::BALANCE->value && (!$user || $user->balance < $fullPrice['Amount'])) {
//            return [
//                'success' => false,
//                'message' => 'Insufficient balance.',
//                'balance' => $user->balance ?? 0,
//                'price' => $fullPrice['Amount']
//            ];
//        }

        // Build AeroBook request (no need for flight offer data)
        $aeroBookRequest = (new AeroBookRequestBuilder($validatedData))->build();

        // Send booking request to XMLAgency
        $aeroBookResponse = $this->xmlAgencyService->sendRequest($aeroBookRequest, 'AeroBook');
dd($aeroBookResponse);
        if (!isset($aeroBookResponse['AeroBookResult']) || $aeroBookResponse['AeroBookResult']['Success'] !== true) {
            $errorMessage = $aeroBookResponse['AeroBookResult']['ErrorString'] ?? 'Booking failed';
            return [
                'success' => false,
                'message' => $errorMessage,
                'data' => $aeroBookResponse
            ];
        }

        $bookingResult = $aeroBookResponse['AeroBookResult'];

        // Extract actual price from booking response
        $actualPrice = [
            'Amount' => $bookingResult['FullPrice'] ?? 0,
            'Currency' => $bookingResult['Currency'] ?? 'EUR'
        ];

        // Extract flight data from booking response
        $offers = $bookingResult['Offers']['OfferInfo'] ?? [];
        $firstOffer = is_array($offers) && isset($offers[0]) ? $offers[0] : $offers;

        // Build outward and return data from booking response
        $segments = $firstOffer['Segments']['OfferSegment'] ?? [];
        $outwardData = null;
        $returnData = null;

        if (!empty($segments)) {
            // For XMLAgency, we'll store the segments as they come
            $outwardData = ['Segments' => $segments];
            // If round-trip, the segments will include both directions
        }

        // Create booking record
        $bookingData = [
            'user_id' => $user?->id ?? null,
            'booking_reference' => $bookingResult['BookId'], // Using BookId as booking_reference
            'supplier_reference' => $bookingResult['BookGuid'], // Using BookGuid as supplier_reference
            'flight_type' => 'XMLAgency',
            'origin' => ['Code' => 'Unknown'], // Will be extracted from segments if needed
            'destination' => ['Code' => 'Unknown'], // Will be extracted from segments if needed
            'outward' => $outwardData,
            'return' => $returnData,
            'price' => $actualPrice,
            'payment_type' => $validatedData['payment_type'],
            'status' => BookingStatus::PENDING->value,
        ];

        $booking = FlightBooking::create($bookingData);

        if (!$booking) {
            throw new \Exception('Failed to create booking');
        }

        // Create contact details
        if (!empty($validatedData['contact_details'])) {
            $booking->contactDetail()->create($validatedData['contact_details']);
        }

        // Create travellers
        if (!empty($validatedData['travellers'])) {
            $booking->travellers()->createMany($validatedData['travellers']);
        }

        Log::info('XMLAgency booking created', [
            'booking_reference' => $booking->booking_reference,
            'supplier_reference' => $booking->supplier_reference,
            'price' => $fullPrice
        ]);

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
        $booking = FlightBooking::with('tickets')
            ->where('booking_reference', $bookId)
            ->where('flight_type', 'XMLAgency')
            ->first();

        return $booking ? ['success' => true, 'data' => $booking] : ['success' => false, 'message' => 'Booking not found'];
    }
}
