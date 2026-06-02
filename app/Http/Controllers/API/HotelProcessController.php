<?php

namespace App\Http\Controllers\API;

use App\DTO\HotelBookRequestDTO;
use App\Exceptions\ETG\BookingFailedException;
use App\Http\Controllers\BaseController;
use App\Http\Requests\API\HotelBookRequest;
use App\Mail\HotelPostpayProcessingMail;
use App\Models\HotelBooking;
use App\Services\ETG\HotelBookingService;
use App\Services\StripePaymentService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HotelProcessController extends BaseController
{
    public function __construct(
        private readonly HotelBookingService $bookingService,
        private readonly StripePaymentService $stripePaymentService,
    ) {}

    /**
     * Process hotel booking details and prepare for payment/confirmation.
     * This is step 1 of the 2-step flow.
     * Creates a temporary booking record and initiates Stripe checkout if needed.
     *
     * @param HotelBookRequest $request
     * @return JsonResponse
     */
    public function processDetails(HotelBookRequest $request): JsonResponse
    {
        $dto = HotelBookRequestDTO::fromRequest($request->validated());
        $user = $this->getAuthenticatedUser();

        if ($dto->paymentType === 'balance' && !$user) {
            return $this->errorResponse('You must be logged in to use balance payment.', 403);
        }

        try {
            $result = $this->bookingService->processDetails(
                $dto,
                userIp:    $request->ip() ?? '127.0.0.1',
                userAgent: $request->userAgent() ?? '',
                user:      $user,
            );

            // Calculate guest counts and rooms count
            $adultsCount = 0;
            $roomsCount = is_array($dto->rooms) ? count($dto->rooms) : 0;

            foreach ($dto->rooms ?? [] as $room) {
                if (isset($room['guests']) && is_array($room['guests'])) {
                    foreach ($room['guests'] as $guest) {
                        $adultsCount++;
                    }
                }
            }

            // Extract hotel_id and room_type from prebook if available
            $hotelId = null;
            $roomType = null;
            if (isset($result['prebook'])) {
                $hotelId = $result['prebook']['hotel_id'] ?? null;
                $roomType = $result['prebook']['room_name'] ?? null;
            }

            // Create temporary booking record for tracking
            $booking = HotelBooking::create([
                'user_id'          => $user?->id,
                'partner_order_id' => $result['partner_order_id'],
                'etg_order_id'     => $result['order_id'],
                'status'           => 'processing',
                'payment_type'     => $dto->paymentType,
                'book_hash'        => $result['book_hash'],
                'amount'           => (float) $result['amount'],
                'currency'         => $result['currency'],
                'hotel_id'         => $hotelId,
                'room_type'        => $roomType,
                'rooms_count'      => $roomsCount,
                'adults_count'     => $adultsCount,
                'children_count'   => 0,
                'contact_email'    => $dto->contact['email'] ?? '',
                'contact_phone'    => $dto->contact['phone'] ?? '',
                'guests'           => $dto->rooms ?? [],
                'api_response'     => $result,
            ]);

            // If Stripe payment, create checkout session
            if ($dto->paymentType === 'stripe') {
                $stripeResult = $this->stripePaymentService->createCheckoutSession($booking, $user);

                if (!$stripeResult['success']) {
                    Log::error('Hotel Stripe checkout creation failed', [
                        'partner_order_id' => $result['partner_order_id'],
                        'message' => $stripeResult['message'],
                    ]);

                    return response()->json([
                        'success' => false,
                        'code' => 'STRIPE_ERROR',
                        'message' => $stripeResult['message'],
                    ], 400);
                }

                // Save session ID to booking
                $booking->update(['stripe_session_id' => $stripeResult['session_id']]);

                Log::info('Hotel Stripe checkout created', [
                    'partner_order_id' => $result['partner_order_id'],
                    'session_id' => $stripeResult['session_id'],
                ]);
            }

            // Build clean response with only necessary fields
            $response = [
                'partner_order_id' => $result['partner_order_id'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
                'payment_type' => $dto->paymentType,
            ];

            // Add stripe fields only if payment type is stripe
            if ($dto->paymentType === 'stripe') {
                $response['checkout_url'] = $stripeResult['checkout_url'];
                $response['session_id'] = $stripeResult['session_id'];
            }

            // Send postpay processing email if payment type is postpay
            if ($dto->paymentType === 'postpay') {
                try {
                    Mail::to($dto->contact['email'])->send(new HotelPostpayProcessingMail($booking));

                    Log::info('Hotel postpay processing email sent', [
                        'partner_order_id' => $result['partner_order_id'],
                        'email' => $dto->contact['email'],
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Hotel postpay processing email failed.', [
                        'partner_order_id' => $result['partner_order_id'],
                        'message' => $e->getMessage(),
                    ]);
                    // Don't fail the process if email fails
                }
            }

            return response()->json([
                'success' => true,
                'data' => $response,
            ], 200);

        } catch (BookingFailedException $e) {
            return response()->json([
                'success' => false,
                'code'    => $e->errorCode,
                'message' => $e->getMessage(),
            ], 422);
        } catch (RequestException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'SERVICE_UNAVAILABLE',
                'message' => 'Hotel booking service is temporarily unavailable. Please try again.',
            ], 503);
        } catch (Exception $e) {
            Log::error('Hotel process: unexpected error.', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'code'    => 'INTERNAL_ERROR',
                'message' => 'An unexpected error occurred. Please try again.',
            ], 500);
        }
    }
}
