<?php

namespace App\Http\Controllers\XMLAgency;

use App\Enum\BookingStatus;
use App\Enum\PaymentType;
use App\Http\Controllers\BaseController;
use App\Http\Requests\XMLAgency\FlightBookRequest;
use App\Http\Resources\FlightBookingResource;
use App\Models\FlightBooking;
use App\Repositories\AirportDataRepositoryInterface;
use App\Services\StripePaymentService;
use App\Services\XMLAgency\FlightBookService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FlightBookController extends BaseController
{
    private array $airports;
    private array $countries;

    public function __construct(
        protected FlightBookService $flightBookService,
        protected AirportDataRepositoryInterface $airportDataRepository,
        protected StripePaymentService $stripePaymentService
    ) {
        $this->airports = $this->airportDataRepository->getAllAirports();
        $this->countries = $this->airportDataRepository->getAllCountries();
    }

    /**
     * Process flight booking and create initial booking record
     *
     * @param FlightBookRequest $request
     * @return JsonResponse
     */
    public function processBooking(FlightBookRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $user = $this->getAuthenticatedUser();

        if ($validatedData['payment_type'] === PaymentType::BALANCE->value && !$user) {
            return $this->errorResponse('You must be logged in to use balance payment.', 403);
        }

        try {
            $response = $this->flightBookService->processBooking($validatedData, $user);
            if (!$response['success']) {
                return response()->json($response, 400);
            }

            return response()->json([
                'success' => true,
                'data' => new FlightBookingResource($response['booking'])
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }



    /**
     * Get booking details
     *
     * @param string $bookId
     * @return JsonResponse
     */
    public function details(string $bookId): JsonResponse
    {
        return response()->json($this->flightBookService->getBookingDetails($bookId));
    }

    /**
     * Get bookings for XMLAgency
     *
     * @localizationHeader
     *
     * @return JsonResponse
     */
    public function getBookings(): JsonResponse
    {
        $bookings = Auth::user()->flightBookings()
            ->where('flight_type', 'XMLAgency')
            ->with('tickets')
            ->latest()
            ->get()
            ->map(fn($booking) => $this->formatBooking($booking));

        return response()->json(['data' => $bookings]);
    }

    private function formatBooking($booking): array
    {
        // Extract outward flight segments (XMLAgency format)
        $outwardSegments = $booking->outward['Segments'] ?? [];
        $firstOutwardSegment = $outwardSegments[0] ?? null;
        $lastOutwardSegment = end($outwardSegments) ?: $firstOutwardSegment;

        // Extract return flight segments if available
        $returnSegments = $booking->return['Segments'] ?? null;
        $firstReturnSegment = $returnSegments[0] ?? null;
        $lastReturnSegment = $returnSegments ? end($returnSegments) : null;

        $defaultFeatures = [
            "HoldBag" => false,
            "CabinBag" => false,
            "FlightChange" => false,
            "Cancellation" => false
        ];

        return [
            'id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'supplier_reference' => $booking->supplier_reference,
            'outward' => [
                'origin' => $this->formatAirport($firstOutwardSegment['Departure']['Iata'] ?? null),
                'destination' => $this->formatAirport($lastOutwardSegment['Arrival']['Iata'] ?? null),
                'departureDate' => $this->splitDateTime($firstOutwardSegment['Departure']['Date'] ?? null),
                'arriveDate' => $this->splitDateTime($lastOutwardSegment['Arrival']['Date'] ?? null),
                'travelClass' => $firstOutwardSegment['FlightClass'] ?? null,
                'features' => $booking->outward['Features'] ?? $defaultFeatures
            ],
            'return' => $returnSegments ? [
                'origin' => $this->formatAirport($firstReturnSegment['Departure']['Iata'] ?? null),
                'destination' => $this->formatAirport($lastReturnSegment['Arrival']['Iata'] ?? null),
                'departureDate' => $this->splitDateTime($firstReturnSegment['Departure']['Date'] ?? null),
                'arriveDate' => $this->splitDateTime($lastReturnSegment['Arrival']['Date'] ?? null),
                'travelClass' => $firstReturnSegment['FlightClass'] ?? null,
                'features' => $booking->return['Features'] ?? $defaultFeatures
            ] : null,
            'price' => $booking->price,
            'status' => $booking->status,
            'features' => $booking->features,
            'tickets' => $booking->tickets->map(fn($ticket) => [
                'id' => $ticket->id,
                'name' => $ticket->name,
                'ticket_url' => $ticket->ticket_url
            ]),
        ];
    }

    /**
     * Format airport details including country
     */
    private function formatAirport(?string $code): ?array
    {
        if (!$code || !isset($this->airports[$code])) {
            return null;
        }

        $locale = App::getLocale();
        $airportData = $this->airports[$code];
        $countryCode = $airportData['country'] ?? null;

        return [
            'code' => $code,
            'airport' => $airportData['airportName'][$locale]
                ?? $airportData['cityName'][$locale]
                ?? $airportData['airportName']['en']
                ?? $airportData['cityName']['en'],
            'country' => isset($this->countries[$countryCode])
                ? ($this->countries[$countryCode]['name'][$locale]
                    ?? $this->countries[$countryCode]['name']['en'])
                : null
        ];
    }

    /**
     * Split date and time from XMLAgency format
     */
    private function splitDateTime(?string $dateTime): ?array
    {
        if (!$dateTime) {
            return null;
        }

        // XMLAgency uses format like "14.08.2018 07:55"
        if (preg_match('/^(\d{2}\.\d{2}\.\d{4})\s+(\d{2}:\d{2})/', $dateTime, $matches)) {
            return ['date' => $matches[1], 'time' => $matches[2]];
        }

        return null;
    }

    /**
     * Create Stripe checkout session for booking
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createStripePaymentIntent(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'booking_reference' => 'required|string|exists:flight_bookings,booking_reference'
            ]);

            $booking = FlightBooking::where('booking_reference', $request->booking_reference)
                ->with('contactDetail')
                ->first();


            $user = $this->getAuthenticatedUser();

            if ($booking->payment_type !== PaymentType::STRIPE) {
                return $this->errorResponse('This booking is not configured for Stripe payment.', 400);
            }

            // Allow anonymous payments - only check ownership if user is logged in
            if ($user && $booking->user_id !== $user->id) {
                return $this->errorResponse('You can only create checkout session for your own bookings.', 403);
            }

            // For anonymous bookings, user_id should be null
            if (!$user && $booking->user_id !== null) {
                return $this->errorResponse('This booking belongs to a registered user.', 403);
            }

            $result = $this->stripePaymentService->createCheckoutSession($booking, $user);

            if (!$result['success']) {
                return $this->errorResponse($result['message'], 400);
            }

            // Store the session ID in the booking
            $booking->update(['stripe_session_id' => $result['session_id']]);

            return response()->json([
                'success' => true,
                'checkout_url' => $result['checkout_url'],
                'session_id' => $result['session_id']
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
