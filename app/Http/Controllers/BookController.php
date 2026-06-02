<?php

namespace App\Http\Controllers;

use App\Enum\BookingStatus;
use App\Enum\FlightSupplier;
use App\Enum\PaymentType;
use App\Http\Requests\Tfusion\StartBookingRequest;
use App\Jobs\Nemo\GenerateTicketJob;
use App\Jobs\TFusion\CheckBookingStatusJob;
use App\Jobs\TFusion\StartBookingJob;
use App\Jobs\XmlAgency\ConfirmBookingJob;
use App\Models\FlightBooking;
use App\Services\StripePaymentService;
use App\Services\TravelFusion\FlightBookService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\MyAgent\PayBookingJob as MyAgentPayBookingJob;

class BookController extends BaseController
{
    public function __construct(
        protected FlightBookService              $flightBookService,
        protected StripePaymentService           $stripePaymentService
    ) {
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
                'booking_reference' => 'required|exists:flight_bookings,booking_reference'
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

            if ($booking->status != BookingStatus::PENDING && $booking->status != BookingStatus::BOOKING_IN_PROGRESS) {
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

            if (in_array($booking->payment_type, [PaymentType::BALANCE, PaymentType::STRIPE])) {
                switch ($booking->flight_type) {
                    case FlightSupplier::TFUSION:
                        StartBookingJob::dispatch($booking);
                        CheckBookingStatusJob::dispatch($booking);
                        break;
                    case FlightSupplier::XMLAGENCY:
                        ConfirmBookingJob::dispatch($booking);
                        break;
                    case FlightSupplier::NEMO:
                        GenerateTicketJob::dispatch($booking);
                        break;
                    case FlightSupplier::MYAGENT:
                        MyAgentPayBookingJob::dispatch($booking);
                        break;
                }
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

}
