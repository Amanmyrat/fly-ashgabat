<?php

namespace App\Services\MyAgent;

use App\Models\FlightBooking;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MyAgentTicketDocumentService
{
    private const MAX_ATTEMPTS = 5;
    private const RETRY_SLEEP_SECONDS = 3;

    public function fetchTicketAndStore(
        FlightBooking $booking,
        string $ticketUrl,
        string $passengerName
    ): ?string {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = Http::timeout(90)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'allow_redirects' => true,
                ])
                ->withHeaders([
                    'Accept-Encoding' => 'gzip, deflate',
                    'Accept' => 'application/pdf,text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/124 Safari/537.36',
                    'Referer' => 'https://api-dev.myagent.online/',
                    'Origin' => 'https://api-dev.myagent.online',
                ])
                ->get($ticketUrl);

            if ($this->responseIsPdf($response)) {
                return $this->storePdfBody($booking, $passengerName, $response->body());
            }

            Log::channel('myagent')->warning('MyAgent ticket PDF download failed or not ready.', [
                'booking_reference' => $booking->booking_reference,
                'attempt' => $attempt,
                'http_status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'body_size' => strlen($response->body()),
                'body_preview' => substr($response->body(), 0, 500),
                'ticket_url' => $ticketUrl,
            ]);

            if ($attempt < self::MAX_ATTEMPTS) {
                sleep(self::RETRY_SLEEP_SECONDS);
            }
        }

        return null;
    }

    private function storePdfBody(FlightBooking $booking, string $passengerName, string $binary): ?string
    {
        try {
            $relativePath = sprintf(
                'tickets/myagent/%s/%s-%s.pdf',
                $booking->booking_reference,
                Str::slug($passengerName) ?: 'passenger',
                now()->timestamp
            );

            Storage::disk('public')->put($relativePath, $binary);

            return Storage::disk('public')->url($relativePath);
        } catch (\Throwable $e) {
            Log::channel('myagent')->error('MyAgent ticket PDF store failed.', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function responseIsPdf(Response $response): bool
    {
        if (!$response->successful()) {
            return false;
        }

        $body = $response->body();

        if (strlen($body) >= 5 && str_starts_with($body, '%PDF')) {
            return true;
        }

        $ct = strtolower($response->header('Content-Type'));

        return str_contains($ct, 'application/pdf') && str_contains($body, '%PDF');
    }

}
