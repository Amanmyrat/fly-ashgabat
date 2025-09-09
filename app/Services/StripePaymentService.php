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
     * @param FlightBooking $booking
     * @param User|null $user
     * @return array
     */
    public function createCheckoutSession(FlightBooking $booking, ?User $user = null): array
    {
        try {
            $amount = $booking->price['Amount'] * 100; // Convert to cents
            $currency = strtolower($booking->price['Currency']);

            Log::channel('stripe')->info('Creating checkout session', [
                'booking_reference' => $booking->booking_reference,
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
                            'name' => 'Flight Booking',
                            'description' => "Booking Reference: {$booking->booking_reference}",
                        ],
                        'unit_amount' => $amount,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => config('app.frontend_url') . "/flight/book/{$booking->booking_reference}?payment=success&session_id={CHECKOUT_SESSION_ID}",
                'cancel_url' => config('app.frontend_url') . "/flight/book?payment=cancelled&booking_reference={$booking->booking_reference}",
                'metadata' => [
                    'booking_reference' => $booking->booking_reference,
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
                $sessionData['customer_email'] = $booking->contactDetail->email;
            }

            $session = Session::create($sessionData);

            Log::channel('stripe')->info('Checkout session created successfully', [
                'session_id' => $session->id,
                'booking_reference' => $booking->booking_reference,
                'checkout_url' => $session->url,
                'customer_type' => $user ? 'registered' : 'anonymous'
            ]);

            return [
                'success' => true,
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ];

        } catch (ApiErrorException $e) {
            Log::channel('stripe')->error('Failed to create checkout session', [
                'booking_reference' => $booking->booking_reference,
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
