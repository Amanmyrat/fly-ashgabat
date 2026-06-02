<?php

namespace App\Services;

use App\Models\HotelBooking;
use App\Services\ETG\EtgClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HotelBookingDocumentService
{
    /** ETG may return these while the voucher is being prepared — safe to retry. */
    private const VOUCHER_RETRY_ERRORS = [
        'pending',
        'failed_to_generate_document',
        'voucher_is_not_downloadable',
    ];

    private const VOUCHER_MAX_ATTEMPTS = 10;

    private const VOUCHER_RETRY_SLEEP_SECONDS = 4;

    public function __construct(private readonly EtgClient $etgClient) {}

    /**
     * Download the official ETG voucher PDF, store on the public disk, and persist URL on the booking.
     *
     * @return string|null Public URL, or null if the voucher was not available in time
     */
    public function fetchVoucherAndStore(HotelBooking $booking, string $language): ?string
    {
        for ($attempt = 1; $attempt <= self::VOUCHER_MAX_ATTEMPTS; $attempt++) {
            $response = $this->etgClient->getVoucherDownload($booking->partner_order_id, $language);

            if ($this->responseIsPdf($response)) {
                return $this->storePdfBody($booking, $response->body());
            }

            $error = $this->parseVoucherError($response);
            if ($error !== null && in_array($error, self::VOUCHER_RETRY_ERRORS, true) && $attempt < self::VOUCHER_MAX_ATTEMPTS) {
                Log::info('Hotel voucher not ready yet; retrying.', [
                    'partner_order_id' => $booking->partner_order_id,
                    'attempt'          => $attempt,
                    'error'            => $error,
                ]);
                sleep(self::VOUCHER_RETRY_SLEEP_SECONDS);

                continue;
            }

            Log::warning('Hotel voucher download failed.', [
                'partner_order_id' => $booking->partner_order_id,
                'http_status'      => $response->status(),
                'error'            => $error,
                'body_preview'     => substr($response->body(), 0, 200),
            ]);

            return null;
        }

        return null;
    }

    private function storePdfBody(HotelBooking $booking, string $binary): ?string
    {
        try {
            $relativePath = 'hotel-bookings/' . $booking->partner_order_id . '-' . $booking->id . '.pdf';
            Storage::disk('public')->put($relativePath, $binary);

            $url = Storage::disk('public')->url($relativePath);
            $booking->forceFill(['confirmation_pdf_url' => $url])->save();

            return $url;
        } catch (\Throwable $e) {
            Log::error('Hotel voucher PDF store failed.', [
                'booking_id' => $booking->id,
                'message'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function responseIsPdf(Response $response): bool
    {
        if (!$response->successful()) {
            return false;
        }

        $ct = strtolower((string) $response->header('Content-Type'));
        if (str_contains($ct, 'application/pdf')) {
            return true;
        }

        $body = $response->body();
        if (strlen($body) >= 5 && str_starts_with($body, '%PDF')) {
            return true;
        }

        return false;
    }

    private function parseVoucherError(Response $response): ?string
    {
        $json = $response->json();
        if (!is_array($json)) {
            return null;
        }

        if (isset($json['error'])) {
            return is_string($json['error']) ? $json['error'] : null;
        }

        return null;
    }

    /** Absolute path to stored PDF for mail attachment, or null. */
    public function absolutePathFromUrl(?string $publicUrl): ?string
    {
        if ($publicUrl === null || $publicUrl === '') {
            return null;
        }

        $path = parse_url($publicUrl, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        $path = urldecode($path);
        if (! str_starts_with($path, '/storage/')) {
            return null;
        }

        $relative = substr($path, strlen('/storage/'));
        $full     = storage_path('app/public/' . $relative);

        return is_file($full) ? $full : null;
    }
}
