<?php

namespace App\Services;

use App\Models\FlightBooking;
use App\Models\User;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Illuminate\Support\Facades\Log;

class StripePaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    /**
     * Create a Stripe Checkout session for the booking
     *
     * @param FlightBooking|HotelBooking $booking
     * @param User|null $user
     * @return array
     */
    public function createCheckoutSession($booking, ?User $user = null): array
    {
        try {
            // Handle both FlightBooking (price is array) and HotelBooking (amount/currency are direct)
            if (isset($booking->price) && is_array($booking->price)) {
                // FlightBooking
                $amount = $booking->price['Amount'] * 100;
                $currency = strtolower($booking->price['Currency']);
                $bookingRef = $booking->booking_reference;
                $description = "Booking Reference: {$booking->booking_reference}";
            } else {
                // HotelBooking
                $amount = $booking->amount * 100;
                $currency = strtolower($booking->currency);
                $bookingRef = $booking->partner_order_id;
                $description = "Hotel Booking: {$booking->partner_order_id}";
            }

            $bookingType = $booking::class;

            Log::channel('stripe')->info('Creating checkout session', [
                'booking_ref' => $bookingRef,
                'booking_type' => $bookingType,
                'user_id' => $user?->id ?? 'anonymous',
                'amount' => $amount,
                'currency' => $currency
            ]);

            $sessionData = [
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => strpos($bookingType, 'Flight') ? 'Flight Booking' : 'Hotel Booking',
                            'description' => $description,
                        ],
                        'unit_amount' => $amount,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => config('app.frontend_url') . $this->getSuccessUrl($booking, $bookingRef),
                'cancel_url' => config('app.frontend_url') . $this->getCancelUrl($booking, $bookingRef),
                'metadata' => [
                    'booking_ref' => $bookingRef,
                    'booking_type' => strpos($bookingType, 'Flight') ? 'flight' : 'hotel',
                    'user_id' => $user?->id ?? 'anonymous',
                ],
            ];

            // Add customer if user is logged in
            if ($user) {
                $sessionData['customer'] = $user->createOrGetStripeCustomer()->id;
            } else {
                // For anonymous users, let Stripe collect email
                $sessionData['customer_creation'] = 'always';
                
                // Pre-fill customer email from contact details
                $email = $booking->contact_email ?? ($booking->contactDetail->email ?? null);
                if ($email) {
                    $sessionData['customer_email'] = $email;
                }
            }

            $session = Session::create($sessionData);

            Log::channel('stripe')->info('Checkout session created successfully', [
                'session_id' => $session->id,
                'booking_ref' => $bookingRef,
                'checkout_url' => $session->url,
                'customer_type' => $user ? 'registered' : 'anonymous'
            ]);

            return [
                'success' => true,
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ];

        } catch (ApiErrorException $e) {
            $ref = $booking->booking_reference ?? $booking->partner_order_id ?? 'unknown';
            
            Log::channel('stripe')->error('Failed to create checkout session', [
                'booking_ref' => $ref,
                'user_id' => $user?->id ?? 'anonymous',
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
                'error_type' => $e->getError()?->type ?? 'unknown'
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create checkout session: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get the success URL based on booking type
     */
    private function getSuccessUrl($booking, string $bookingRef): string
    {
        $bookingType = $booking::class;
        
        if (strpos($bookingType, 'Flight')) {
            return "/flight/book/{$bookingRef}?payment=success&session_id={CHECKOUT_SESSION_ID}";
        } else {
            return "/hotel/book/{$bookingRef}?payment=success&session_id={CHECKOUT_SESSION_ID}";
        }
    }

    /**
     * Get the cancel URL based on booking type
     */
    private function getCancelUrl($booking, string $bookingRef): string
    {
        $bookingType = $booking::class;
        
        if (strpos($bookingType, 'Flight')) {
            return "/flight/book?payment=cancelled&booking_reference={$bookingRef}";
        } else {
            return "/hotel/book?payment=cancelled&partner_order_id={$bookingRef}";
        }
    }

    /**
     * Verify if Stripe checkout session payment was successful
     *
     * @param string $sessionId
     * @return array
     */
    public function verifyPaymentStatus(string $sessionId): array
    {
        try {
            Log::channel('stripe')->info('Verifying payment status', ['session_id' => $sessionId]);
            
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            
            Log::channel('stripe')->info('Payment status retrieved', [
                'session_id' => $sessionId,
                'payment_status' => $session->payment_status,
                'status' => $session->status
            ]);
            
            // Check if payment was actually completed
            if ($session->payment_status === 'paid' && $session->status === 'complete') {
                return [
                    'success' => true,
                    'paid' => true,
                    'session' => $session
                ];
            } else {
                return [
                    'success' => true,
                    'paid' => false,
                    'payment_status' => $session->payment_status,
                    'status' => $session->status,
                    'message' => "Payment not completed. Status: {$session->payment_status}"
                ];
            }

        } catch (ApiErrorException $e) {
            Log::channel('stripe')->error('Failed to verify payment status', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ]);
            
            return [
                'success' => false,
                'paid' => false,
                'message' => 'Failed to verify payment: ' . $e->getMessage(),
            ];
        }
    }
}
