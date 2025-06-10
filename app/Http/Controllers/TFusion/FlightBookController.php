<?php

namespace App\Http\Controllers\TFusion;

use App\Enum\BookingStatus;
use App\Enum\PaymentType;
use App\Http\Controllers\BaseController;
use App\Http\Requests\FlightBookRequest;
use App\Http\Requests\StartBookingRequest;
use App\Http\Resources\FlightBookingResource;
use App\Jobs\CheckBookingStatusJob;
use App\Jobs\StartBookingJob;
use App\Models\FlightBooking;
use App\Repositories\AirportDataRepositoryInterface;
use App\Services\FlightBookService;
use App\Services\StripePaymentService;
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
            $response = $this->flightBookService->processTerms($validatedData, $user);
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
     * Start the actual booking process
     *
     * @param StartBookingRequest $request
     * @return JsonResponse
     */
    public function startBooking(StartBookingRequest $request): JsonResponse
    {
        try {
            $booking = $request->getBooking();
            $user = $this->getAuthenticatedUser();

            if ($booking->status != BookingStatus::PENDING) {
                return $this->errorResponse('Booking is not in pending status', 400);
            }

            // Check ownership for registered users
            if ($user && $booking->user_id !== $user->id) {
                return $this->errorResponse('You can only start your own bookings.', 403);
            }

            // Handle Stripe payment verification
            if ($booking->payment_type == PaymentType::STRIPE) {
                    $sessionId = $request->validated('session_id');

                // Validate session ID is provided
                if (empty($sessionId)) {
                    return $this->errorResponse('Stripe session ID is required for Stripe payments.', 400);
                }

                // Verify session ID matches booking
                if ($booking->stripe_session_id !== $sessionId) {
                    return $this->errorResponse('Stripe session ID does not match booking.', 400);
                }

                // 🔒 SECURITY: Verify payment actually succeeded with Stripe
                $paymentVerification = $this->stripePaymentService->verifyPaymentStatus($sessionId);

                if (!$paymentVerification['success']) {
                    return $this->errorResponse($paymentVerification['message'], 400);
                }

                if (!$paymentVerification['paid']) {
                    return $this->errorResponse(
                        $paymentVerification['message'] ?? 'Payment was not completed successfully.',
                        400
                    );
                }

                // Payment verified - safe to proceed
                Log::info('Stripe payment verified for booking', [
                    'booking_reference' => $booking->booking_reference,
                    'session_id' => $sessionId,
                    'payment_status' => 'verified_paid'
                ]);
            }

            // Start booking process for BALANCE, STRIPE, or POST_PAY
            if (in_array($booking->payment_type, [PaymentType::BALANCE, PaymentType::STRIPE, PaymentType::POST_PAY])) {
                StartBookingJob::dispatch($booking);
                CheckBookingStatusJob::dispatch($booking);
            }

            return response()->json([
                'success' => true,
                'message' => 'Booking process started successfully'
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
     * Get bookings
     *
     * @localizationHeader
     *
     * @return JsonResponse
     */
    public function getBookings(): JsonResponse
    {
        $bookings = Auth::user()->flightBookings()
            ->with('tickets')
            ->latest()
            ->get()
            ->map(fn($booking) => $this->formatBooking($booking));

        return response()->json(['data' => $bookings]);
    }

    private function formatBooking($booking): array
    {
        // Extract outward flight segments
        $outwardSegments = $booking->outward['SegmentList']['Segment'] ?? [];
        $firstOutwardSegment = $outwardSegments[0] ?? null;
        $lastOutwardSegment = end($outwardSegments) ?: $firstOutwardSegment;

        // Extract return flight segments if available
        $returnSegments = $booking->return['SegmentList']['Segment'] ?? null;
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
                'origin' => $this->formatAirport($firstOutwardSegment['Origin']['Code'] ?? null),
                'destination' => $this->formatAirport($lastOutwardSegment['Destination']['Code'] ?? null),
                'departureDate' => $this->splitDateTime($firstOutwardSegment['DepartDate'] ?? null),
                'arriveDate' => $this->splitDateTime($lastOutwardSegment['ArriveDate'] ?? null),
                'travelClass' => $firstOutwardSegment['TravelClass']['TfClass'] ?? null, // Travel class from first segment
                'features' => $booking->outward['Features'] ?? $defaultFeatures
            ],
            'return' => $returnSegments ? [
                'origin' => $this->formatAirport($firstReturnSegment['Origin']['Code'] ?? null),
                'destination' => $this->formatAirport($lastReturnSegment['Destination']['Code'] ?? null),
                'departureDate' => $this->splitDateTime($firstReturnSegment['DepartDate'] ?? null),
                'arriveDate' => $this->splitDateTime($lastReturnSegment['ArriveDate'] ?? null),
                'travelClass' => $firstReturnSegment['TravelClass']['TfClass'] ?? null, // Travel class for return
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
     * Split date and time from "DD/MM/YYYY-HH:MM" format
     */
    private function splitDateTime(?string $dateTime): ?array
    {
        if (!$dateTime || !str_contains($dateTime, '-')) {
            return null;
        }

        [$date, $time] = explode('-', $dateTime);
        return ['date' => $date, 'time' => $time];
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
