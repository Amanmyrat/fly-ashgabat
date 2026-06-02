<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class XmlBookingTicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected array $tickets,
        protected array $ticketData = []
    ) {
    }

    public function build(): self
    {
        $viewData = array_merge([
            'bookingReference' => null,
            'bookingDate' => null,
            'fullName' => null,
            'passportNumber' => null,
            'dateOfBirth' => null,
            'contactEmail' => null,
            'contactPhone' => null,
            'flightData' => [
                'Outward' => [],
                'Return' => null,
            ],
            'ticketNumber' => null,
            'pnr' => null,
            'tickets' => $this->tickets,
        ], $this->ticketData);

        $email = $this
            ->subject('Вы оплатили заказ на Fly-Ashgabat!')
            ->view('emails.xml_booking_ticket')
            ->with($viewData);

        foreach ($this->tickets as $ticket) {
            $ticketUrl = $ticket['ticket_url'] ?? null;
            $ticketName = $ticket['name'] ?? 'ticket';

            if (!$ticketUrl) {
                Log::error('Ticket URL is missing in XmlBookingTicketMail', [
                    'ticket' => $ticket,
                ]);

                continue;
            }

            $fullFilePath = $this->resolvePublicStoragePath($ticketUrl);

            if (!$fullFilePath || !file_exists($fullFilePath)) {
                Log::error('Ticket file not found for mail attachment', [
                    'ticket_url' => $ticketUrl,
                    'resolved_path' => $fullFilePath,
                ]);

                continue;
            }

            $email->attach($fullFilePath, [
                'as' => Str::slug($ticketName) . '.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $email;
    }

    private function resolvePublicStoragePath(string $ticketUrl): ?string
    {
        $path = parse_url($ticketUrl, PHP_URL_PATH);

        if (!$path) {
            return null;
        }

        /*
         * Example:
         * http://localhost:8080/storage/tickets/myagent/125.../file.pdf
         *
         * parse_url path:
         * /storage/tickets/myagent/125.../file.pdf
         *
         * public disk relative path:
         * tickets/myagent/125.../file.pdf
         */
        $relativePath = Str::after($path, '/storage/');

        if (!$relativePath || $relativePath === $path) {
            return null;
        }

        return Storage::disk('public')->path($relativePath);
    }
}
