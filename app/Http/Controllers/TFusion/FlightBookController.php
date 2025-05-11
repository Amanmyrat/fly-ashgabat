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
use App\Repositories\AirportDataRepositoryInterface;
use App\Services\FlightBookService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class FlightBookController extends BaseController
{
    private array $airports;
    private array $countries;

    public function __construct(protected FlightBookService $flightBookService, protected AirportDataRepositoryInterface $airportDataRepository)
    {
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

        if ($validatedData['payment_type'] === 'balance' && !$user) {
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

            if ($booking->status != BookingStatus::PENDING) {
                return $this->errorResponse('Booking is not in pending status', 400);
            }

            if ($booking->payment_type == PaymentType::BALANCE) {
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
}
