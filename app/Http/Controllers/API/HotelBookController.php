<?php

namespace App\Http\Controllers\API;

use App\DTO\HotelBookRequestDTO;
use App\Exceptions\ETG\BookingFailedException;
use App\Http\Controllers\BaseController;
use App\Http\Requests\API\HotelStartBookingRequest;
use App\Http\Resources\HotelBookingCreatedResource;
use App\Http\Resources\UserHotelBookingResource;
use App\Mail\HotelBookingConfirmationWithVoucherMail;
use App\Models\HotelBooking;
use App\Models\User;
use App\Services\ETG\HotelBookingService;
use App\Services\HotelBookingDocumentService;
use App\Services\StripePaymentService;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HotelBookController extends BaseController
{
    public function __construct(
        private readonly HotelBookingService $bookingService,
        private readonly HotelBookingDocumentService $documentService,
        private readonly StripePaymentService $stripePaymentService,
    ) {}

    /** Start booking.
     *
     * @localizationHeader
     *
     */
    public function startBooking(HotelStartBookingRequest $request): JsonResponse
    {
        try {
            $booking = $request->getBooking();
            $user = $this->getAuthenticatedUser();

            if (!$booking) {
                return $this->errorResponse('Booking not found', 404);
            }

            if (!$this->isBookingInPendingStatus($booking)) {
                return $this->errorResponse('Booking is not in pending status', 400);
            }

            if (!$this->isBookingOwnedByUser($booking, $user)) {
                return $this->errorResponse('You can only start your own bookings.', 403);
            }

            if (!$this->verifyPayment($booking, $request, $user)) {
                return $this->getPaymentVerificationError($booking);
            }

            $etgResult = $this->confirmBookingWithEtg($booking, $request, $user);

            if (!$etgResult) {
                return $this->getEtgConfirmationError($booking);
            }

            $this->fetchVoucher($booking);
            $this->sendConfirmationEmail($booking);
            $booking->refresh();

            return response()->json([
                'success' => true,
                'data' => new HotelBookingCreatedResource($booking),
                'message' => 'Booking confirmed successfully'
            ], 200);

        } catch (Exception $e) {
            Log::error('Hotel start booking error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /** Get booking status.
     *
     * @localizationHeader
     *
     */
    public function status(string $partnerOrderId): JsonResponse
    {
        try {
            $booking = HotelBooking::where('partner_order_id', $partnerOrderId)->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'code' => 'NOT_FOUND',
                    'message' => 'Booking not found',
                ], 404);
            }

            $etgStatus = $this->bookingService->getOrderStatus($partnerOrderId);

            return response()->json([
                'success' => true,
                'data' => new HotelBookingCreatedResource($booking),
                'etg_status' => $etgStatus,
            ], 200);

        } catch (RequestException $e) {
            return response()->json([
                'success' => false,
                'code' => 'SERVICE_UNAVAILABLE',
                'message' => 'Could not fetch order status. Please try again.',
            ], 503);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'code' => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /** Get all hotel bookings for logged-in user.
     *
     * @localizationHeader
     *
     */
    public function getUserBookings(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User not authenticated',
                ], 401);
            }

            $bookings = HotelBooking::where('user_id', $user->id)
                ->with('hotel')
                ->latest()
                ->get();

            $data = $bookings->map(fn ($booking) => new UserHotelBookingResource($booking));

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $bookings->count(),
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to fetch user hotel bookings', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'code' => 'ERROR',
                'message' => 'Failed to fetch bookings',
            ], 500);
        }
    }

    private function isBookingInPendingStatus(HotelBooking $booking): bool
    {
        return in_array($booking->status, ['pending', 'processing'], true);
    }

    private function isBookingOwnedByUser(HotelBooking $booking, ?User $user): bool
    {
        if (!$user) {
            return true;
        }

        return $booking->user_id === $user->id;
    }

    private function verifyPayment(HotelBooking $booking, HotelStartBookingRequest $request, ?User $user): bool
    {
        if ($booking->payment_type === 'stripe') {
            return $this->verifyStripePayment($booking, $request);
        }

        if ($booking->payment_type === 'balance') {
            return $this->verifyAndDeductBalance($booking, $user);
        }

        return true;
    }

    private function verifyStripePayment(HotelBooking $booking, HotelStartBookingRequest $request): bool
    {
        $sessionId = $request->validated('session_id');

        if (empty($sessionId)) {
            return false;
        }

        if ($booking->stripe_session_id !== $sessionId) {
            return false;
        }

        $paymentVerification = $this->stripePaymentService->verifyPaymentStatus($sessionId);

        if (!$paymentVerification['success'] || !$paymentVerification['paid']) {
            return false;
        }

        Log::info('Hotel Stripe payment verified', [
            'partner_order_id' => $booking->partner_order_id,
            'session_id' => $sessionId,
        ]);

        return true;
    }

    private function verifyAndDeductBalance(HotelBooking $booking, ?User $user): bool
    {
        if (!$user) {
            Log::warning('Balance payment attempted without user', [
                'partner_order_id' => $booking->partner_order_id,
            ]);
            return false;
        }

        try {
            DB::transaction(function () use ($booking, $user): void {
                $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $amount = (float) $booking->amount;

                if ((float) $user->balance < $amount) {
                    throw new \RuntimeException('Insufficient balance');
                }

                $user->decrement('balance', $amount);

                Log::info('Hotel booking: balance deducted', [
                    'partner_order_id' => $booking->partner_order_id,
                    'amount' => $amount,
                ]);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('Hotel booking: balance deduction failed', [
                'partner_order_id' => $booking->partner_order_id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function confirmBookingWithEtg(HotelBooking $booking, HotelStartBookingRequest $request, ?User $user): ?array
    {
        try {
            $dto = HotelBookRequestDTO::fromRequest([
                'book_hash' => $booking->book_hash,
                'payment_type' => $booking->payment_type,
                'rooms' => $booking->guests ?? [],
                'contact' => [
                    'email' => $booking->contact_email,
                    'phone' => $booking->contact_phone,
                ],
            ]);

            $etgResult = $this->bookingService->startBooking(
                $dto,
                $booking->partner_order_id,
                (float) $booking->amount,
                $booking->currency,
            );

            $booking->update([
                'status' => 'confirmed',
                'api_response' => $etgResult,
            ]);

            Log::info('Hotel booking confirmed with ETG', [
                'partner_order_id' => $booking->partner_order_id,
                'etg_status' => $etgResult['status'] ?? 'unknown',
            ]);

            return $etgResult;

        } catch (BookingFailedException $e) {
            Log::error('Hotel booking ETG confirmation failed', [
                'partner_order_id' => $booking->partner_order_id,
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (RequestException $e) {
            Log::error('Hotel booking ETG service error', [
                'partner_order_id' => $booking->partner_order_id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function fetchVoucher(HotelBooking $booking): void
    {
        try {
            $this->documentService->fetchVoucherAndStore($booking, 'en');
            $booking->refresh();

            usleep(100000);

            Log::info('Hotel booking voucher fetched', [
                'partner_order_id' => $booking->partner_order_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Hotel booking: failed to fetch voucher', [
                'partner_order_id' => $booking->partner_order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendConfirmationEmail(HotelBooking $booking): void
    {
        try {
            Mail::to($booking->contact_email)->send(
                new HotelBookingConfirmationWithVoucherMail($booking)
            );

            Log::info('Hotel booking confirmation email sent', [
                'partner_order_id' => $booking->partner_order_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to send hotel booking confirmation email', [
                'partner_order_id' => $booking->partner_order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getPaymentVerificationError(HotelBooking $booking): JsonResponse
    {
        Log::error('Hotel booking payment verification failed', [
            'partner_order_id' => $booking->partner_order_id,
            'payment_type' => $booking->payment_type,
        ]);

        return response()->json([
            'success' => false,
            'code' => 'PAYMENT_VERIFICATION_FAILED',
            'message' => 'Payment verification failed',
        ], 400);
    }

    private function getEtgConfirmationError(HotelBooking $booking): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 'BOOKING_CONFIRMATION_FAILED',
            'message' => 'Booking confirmation failed',
        ], 422);
    }
}
