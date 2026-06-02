<?php

namespace App\Mail;

use App\Models\HotelBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HotelBookingConfirmationWithVoucherMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public HotelBooking $booking,
    ) {}

    public function build(): self
    {
        $email = $this
            ->subject(__('Hotel booking confirmed - Your voucher is attached'))
            ->view('emails.hotel_confirmation_with_voucher')
            ->with(['booking' => $this->booking]);

        $this->attachVoucher($email);

        return $email;
    }

    private function attachVoucher(self $email): void
    {
        if (empty($this->booking->confirmation_pdf_url)) {
            Log::info('Hotel booking voucher not attached - URL empty', [
                'booking_id' => $this->booking->id ?? 'unknown',
            ]);
            return;
        }

        try {
            $filePath = $this->resolveVoucherPath($this->booking->confirmation_pdf_url);

            Log::debug('Hotel voucher attachment debug', [
                'booking_id' => $this->booking->id,
                'url' => $this->booking->confirmation_pdf_url,
                'resolved_path' => $filePath,
                'exists' => $filePath ? file_exists($filePath) : false,
            ]);

            if ($filePath && file_exists($filePath)) {
                $email->attach($filePath, [
                    'as' => 'hotel-voucher.pdf',
                    'mime' => 'application/pdf',
                ]);

                Log::info('Hotel voucher attached successfully', [
                    'booking_id' => $this->booking->id,
                    'file_size' => filesize($filePath),
                ]);
            } else {
                Log::warning('Hotel voucher file not found', [
                    'booking_id' => $this->booking->id ?? 'unknown',
                    'url' => $this->booking->confirmation_pdf_url,
                    'path' => $filePath,
                    'exists' => $filePath ? file_exists($filePath) : false,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to attach hotel booking voucher', [
                'booking_id' => $this->booking->id ?? 'unknown',
                'url' => $this->booking->confirmation_pdf_url ?? 'empty',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveVoucherPath(?string $publicUrl): ?string
    {
        if (!$publicUrl) {
            return null;
        }

        $parsedPath = parse_url($publicUrl, PHP_URL_PATH);
        if (!is_string($parsedPath) || $parsedPath === '') {
            return null;
        }

        $decodedPath = urldecode($parsedPath);

        if (!str_starts_with($decodedPath, '/storage/')) {
            return null;
        }

        $relativePath = substr($decodedPath, strlen('/storage/'));
        $fullPath = storage_path('app/public/' . $relativePath);

        return is_file($fullPath) ? $fullPath : null;
    }
}
