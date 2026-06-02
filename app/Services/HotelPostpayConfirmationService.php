<?php

namespace App\Services;

use App\DTO\HotelBookRequestDTO;
use App\Mail\HotelBookingConfirmationWithVoucherMail;
use App\Models\HotelBooking;
use App\Services\ETG\HotelBookingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class HotelPostpayConfirmationService
{
    public function __construct(
        private readonly HotelBookingService $bookingService,
        private readonly HotelBookingDocumentService $documentService,
    ) {}

    public function confirmPostpayBooking(HotelBooking $booking): void
    {
        $dto = new HotelBookRequestDTO(
            bookHash: $booking->book_hash,
            paymentType: 'postpay',
            language: 'en',
            rooms: $booking->guests ?? [],
            contact: [
                'email' => $booking->contact_email,
                'phone' => $booking->contact_phone,
            ]
        );

        $etgResult = $this->bookingService->startBooking(
            $dto,
            $booking->partner_order_id,
            $booking->amount,
            $booking->currency,
            '127.0.0.1'
        );

        $booking->update([
            'status' => 'confirmed',
            'api_response' => $etgResult,
        ]);

        $this->fetchVoucher($booking);
        $this->sendConfirmationEmail($booking);

        Log::info('Hotel booking confirmed with ETG (postpay)', [
            'partner_order_id' => $booking->partner_order_id,
            'etg_status' => $etgResult['status'] ?? 'unknown',
        ]);
    }

    private function fetchVoucher(HotelBooking $booking): void
    {
        try {
            $this->documentService->fetchVoucherAndStore($booking, 'en');
            $booking->refresh();

            usleep(100000);

            Log::info('Hotel booking voucher fetched', [
                'partner_order_id' => $booking->partner_order_id,
                'voucher_url' => $booking->confirmation_pdf_url,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to fetch hotel booking voucher', [
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

            Log::info('Hotel booking confirmation email sent with voucher', [
                'partner_order_id' => $booking->partner_order_id,
                'email' => $booking->contact_email,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to send hotel booking confirmation email', [
                'partner_order_id' => $booking->partner_order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
