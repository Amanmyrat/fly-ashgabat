<?php

namespace App\Jobs\MyAgent;

use App\Enum\BookingStatus;
use App\Enum\FlightSupplier;
use App\Enum\PaymentType;
use App\Mail\XmlBookingTicketMail;
use App\Models\FlightBooking;
use App\Models\FlightTicket;
use App\Services\MyAgent\MyAgentService;
use App\Services\MyAgent\MyAgentTicketDocumentService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PayBookingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $maxExceptions = 1;

    public function __construct(protected FlightBooking $booking)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(
        MyAgentService $myAgentService,
        MyAgentTicketDocumentService $ticketDocumentService
    ): void {
        $booking = $this->booking->fresh([
            'user',
            'contactDetail',
            'travellers',
            'tickets',
        ]);

        if (!$booking) {
            return;
        }

        if ($booking->flight_type !== FlightSupplier::MYAGENT) {
            return;
        }

        if ($booking->status === BookingStatus::SUCCEEDED && $booking->tickets()->exists()) {
            Log::channel('myagent')->info('MyAgent booking already succeeded with tickets.', [
                'booking_reference' => $booking->booking_reference,
            ]);

            return;
        }

        if ($booking->payment_type === PaymentType::BALANCE) {
            $this->ensureLocalBalanceIsEnough($booking);
        }

        $booking->update([
            'status' => BookingStatus::BOOKING_IN_PROGRESS->value,
        ]);

        $payResponse = $this->payWithBalance($myAgentService, $booking);

        if (($payResponse['success'] ?? false) !== true) {
            $booking->update([
                'status' => BookingStatus::FAILED->value,
            ]);

            Log::channel('myagent')->error('MyAgent pay-with-balance failed.', [
                'booking_reference' => $booking->booking_reference,
                'response' => $payResponse,
            ]);

            return;
        }

        $payStatus = $payResponse['data']['status'] ?? null;

        $bookInfo = $this->getBookInfo($myAgentService, $booking);

        if (($bookInfo['success'] ?? false) !== true) {
            $booking->update([
                'status' => BookingStatus::FAILED->value,
            ]);

            Log::channel('myagent')->error('MyAgent book-info failed.', [
                'booking_reference' => $booking->booking_reference,
                'response' => $bookInfo,
            ]);

            return;
        }

        $book = $bookInfo['data']['book'] ?? null;

        if (!is_array($book)) {
            $booking->update([
                'status' => BookingStatus::FAILED->value,
            ]);

            Log::channel('myagent')->error('MyAgent book-info response does not contain book data.', [
                'booking_reference' => $booking->booking_reference,
                'response' => $bookInfo,
            ]);

            return;
        }

        $remoteStatus = $book['order']['status']['sign'] ?? $payStatus;
        $localStatus = $this->mapMyAgentStatusToLocal($remoteStatus);

        if ($localStatus !== BookingStatus::SUCCEEDED) {
            $booking->update([
                'status' => $localStatus->value,
            ]);

            Log::channel('myagent')->warning('MyAgent booking is not ticketed after payment.', [
                'booking_reference' => $booking->booking_reference,
                'remote_status' => $remoteStatus,
                'local_status' => $localStatus->value,
            ]);

            return;
        }

        $tickets = $this->storeMyAgentTickets(
            booking: $booking,
            book: $book,
            ticketDocumentService: $ticketDocumentService
        );

        if (empty($tickets)) {
            $booking->update([
                'status' => BookingStatus::FAILED->value,
            ]);

            Log::channel('myagent')->error('MyAgent booking is ticketed, but no ticket PDFs were stored.', [
                'booking_reference' => $booking->booking_reference,
            ]);

            return;
        }

        if ($booking->payment_type === PaymentType::BALANCE) {
            $this->deductLocalBalance($booking);
        }

        $booking->update([
            'status' => BookingStatus::SUCCEEDED->value,
        ]);

        $this->sendTicketEmail($booking, $book, $tickets);

        Log::channel('myagent')->info('MyAgent booking completed successfully.', [
            'booking_reference' => $booking->booking_reference,
            'tickets_count' => count($tickets),
        ]);
    }

    /**
     * @throws Exception
     */
    private function ensureLocalBalanceIsEnough(FlightBooking $booking): void
    {
        $amount = (float) ($booking->price['Amount'] ?? 0);

        if (!$booking->user || $booking->user->balance < $amount) {
            $booking->update([
                'status' => BookingStatus::FAILED->value,
            ]);

            Log::channel('myagent')->warning('Insufficient local balance for MyAgent booking.', [
                'booking_reference' => $booking->booking_reference,
                'user_id' => $booking->user_id,
                'balance' => $booking->user?->balance,
                'price' => $amount,
            ]);

            throw new Exception('Insufficient local balance.');
        }
    }

    private function payWithBalance(MyAgentService $myAgentService, FlightBooking $booking): array
    {
        $response = $myAgentService->get('/payment/pay-with-balance', [
            'billing' => $booking->booking_reference,
        ]);

        Log::channel('myagent')->info('MyAgent pay-with-balance response.', [
            'booking_reference' => $booking->booking_reference,
            'success' => $response['success'] ?? null,
            'status' => $response['data']['status'] ?? null,
            'pid' => $response['pid'] ?? null,
        ]);

        return $response;
    }

    private function getBookInfo(MyAgentService $myAgentService, FlightBooking $booking): array
    {
        $response = $myAgentService->get('/avia/book-info', [
            'billing_number' => $booking->booking_reference,
            'lang' => 'ru',
        ]);

        Log::channel('myagent')->info('MyAgent book-info response.', [
            'booking_reference' => $booking->booking_reference,
            'success' => $response['success'] ?? null,
            'status' => $response['data']['book']['order']['status']['sign'] ?? null,
            'pid' => $response['pid'] ?? null,
        ]);

        return $response;
    }

    private function mapMyAgentStatusToLocal(?string $status): BookingStatus
    {
        return match ($status) {
            'Paid', 'Ticketed' => BookingStatus::SUCCEEDED,
            'Booked', 'OnBooking', 'WaitToBooking', 'OnPayment' => BookingStatus::BOOKING_IN_PROGRESS,
            'Cancelled' => BookingStatus::UNCONFIRMED_BY_SUPPLIER,
            'Refunded' => BookingStatus::UNCONFIRMED,
            default => BookingStatus::FAILED,
        };
    }

    /**
     * MyAgent only:
     * Download official MyAgent PDFs.
     * Do not generate custom PDF fallback.
     */
    private function storeMyAgentTickets(
        FlightBooking $booking,
        array $book,
        MyAgentTicketDocumentService $ticketDocumentService
    ): array {
        if ($booking->tickets()->exists()) {
            return $booking->tickets()
                ->get()
                ->map(fn (FlightTicket $ticket) => [
                    'name' => $ticket->name,
                    'ticket_url' => $ticket->ticket_url,
                ])
                ->toArray();
        }

        $tickets = [];

        foreach (($book['passengers'] ?? []) as $passenger) {
            $fullName = $this->buildPassengerName($passenger);

            $remotePdfUrl = $this->findPassengerTicketUrl($book, $passenger);

            if (!$remotePdfUrl) {
                Log::channel('myagent')->warning('No MyAgent ticket URL found for passenger.', [
                    'booking_reference' => $booking->booking_reference,
                    'passenger' => $fullName,
                    'passenger_key' => $passenger['key'] ?? null,
                    'passenger_uuid' => $passenger['uuid'] ?? null,
                ]);

                continue;
            }

            $storedUrl = $ticketDocumentService->fetchTicketAndStore(
                booking: $booking,
                ticketUrl: $remotePdfUrl,
                passengerName: $fullName
            );

            if (!$storedUrl) {
                Log::channel('myagent')->error('Failed to store MyAgent ticket PDF.', [
                    'booking_reference' => $booking->booking_reference,
                    'passenger' => $fullName,
                    'remote_pdf_url' => $remotePdfUrl,
                ]);

                continue;
            }

            $ticket = FlightTicket::create([
                'booking_id' => $booking->id,
                'name' => $fullName,
                'ticket_url' => $storedUrl,
            ]);

            $tickets[] = [
                'name' => $ticket->name,
                'ticket_url' => $ticket->ticket_url,
            ];
        }

        return $tickets;
    }

    /**
     * Prefer official booking-level ticket_receipt.
     * It is cleaner than passenger eticket_url.
     */
    private function findPassengerTicketUrl(array $book, array $passenger): ?string
    {
        $passengerKey = $passenger['key'] ?? null;
        $passengerUuid = $passenger['uuid'] ?? null;

        foreach (($book['tickets'] ?? []) as $ticket) {
            foreach (($ticket['passengers'] ?? []) as $ticketPassenger) {
                $samePassenger =
                    ($passengerKey && (($ticketPassenger['key'] ?? null) === $passengerKey))
                    || ($passengerUuid && (($ticketPassenger['uuid'] ?? null) === $passengerUuid));

                if ($samePassenger && !empty($ticket['documents']['ticket_receipt'])) {
                    return $ticket['documents']['ticket_receipt'];
                }
            }
        }

        foreach (($book['tickets'] ?? []) as $ticket) {
            if (!empty($ticket['documents']['ticket_receipt'])) {
                return $ticket['documents']['ticket_receipt'];
            }
        }

        if (!empty($passenger['eticket_url'])) {
            return $passenger['eticket_url'];
        }

        foreach (($book['tickets'] ?? []) as $ticket) {
            foreach (($ticket['passengers'] ?? []) as $ticketPassenger) {
                $samePassenger =
                    ($passengerKey && (($ticketPassenger['key'] ?? null) === $passengerKey))
                    || ($passengerUuid && (($ticketPassenger['uuid'] ?? null) === $passengerUuid));

                if ($samePassenger && !empty($ticketPassenger['eticket_url'])) {
                    return $ticketPassenger['eticket_url'];
                }
            }
        }

        return null;
    }

    private function deductLocalBalance(FlightBooking $booking): void
    {
        $booking = $booking->fresh(['user']);

        if (!$booking?->user) {
            return;
        }

        $amount = (float) ($booking->price['Amount'] ?? 0);

        if ($amount <= 0) {
            return;
        }

        $booking->user->decrement('balance', $amount);

        Log::channel('myagent')->info('Local balance deducted for MyAgent booking.', [
            'booking_reference' => $booking->booking_reference,
            'user_id' => $booking->user_id,
            'amount' => $amount,
        ]);
    }

    private function sendTicketEmail(FlightBooking $booking, array $book, array $tickets): void
    {
        $booking = $booking->fresh(['contactDetail']);

        if (!$booking) {
            return;
        }

        $email = $booking->contactDetail?->email
            ?? $book['passengers'][0]['email']
            ?? null;

        if (!$email) {
            Log::channel('myagent')->warning('No email found for MyAgent ticket mail.', [
                'booking_reference' => $booking->booking_reference,
            ]);

            return;
        }

        $ticketData = $this->buildMailTicketData($booking, $book);

        Mail::to($email)->send(new XmlBookingTicketMail($tickets, $ticketData));

        Log::channel('myagent')->info('MyAgent ticket email sent.', [
            'booking_reference' => $booking->booking_reference,
            'email' => $email,
        ]);
    }

    private function buildMailTicketData(FlightBooking $booking, array $book): array
    {
        $firstPassenger = $book['passengers'][0] ?? [];
        $firstTicket = $book['tickets'][0] ?? [];

        return [
            'bookingReference' => $booking->booking_reference,
            'bookingDate' => $this->formatMyAgentDateTime($book['order']['created'] ?? null) ?: now()->format('d.m.Y H:i'),
            'fullName' => $this->buildPassengerName($firstPassenger),
            'passportNumber' => $firstPassenger['document']['num'] ?? '',
            'dateOfBirth' => $firstPassenger['birthdate'] ?? '',
            'contactEmail' => $firstPassenger['email'] ?? $booking->contactDetail?->email ?? '',
            'contactPhone' => $firstPassenger['phone'] ?? $this->formatContactPhone($booking) ?? '',
            'flightData' => $this->buildMailFlightData($book['flight'] ?? []),
            'ticketNumber' => $firstPassenger['ticketData']['number'] ?? null,
            'pnr' => $firstTicket['locator'] ?? null,
            'tickets' => $book['tickets'] ?? [],
        ];
    }

    private function buildMailFlightData(array $flight): array
    {
        $segments = $flight['segments'] ?? [];

        $outward = array_values(array_filter(
            $segments,
            fn (array $segment) => (int) ($segment['direction'] ?? 0) === 0
        ));

        $return = array_values(array_filter(
            $segments,
            fn (array $segment) => (int) ($segment['direction'] ?? 0) === 1
        ));

        if (empty($outward)) {
            $outward = $segments;
        }

        return [
            'Outward' => array_map(fn (array $segment) => $this->buildMailSegment($segment), $outward),
            'Return' => !empty($return)
                ? array_map(fn (array $segment) => $this->buildMailSegment($segment), $return)
                : null,
        ];
    }

    private function buildMailSegment(array $segment): array
    {
        $carrier = $segment['carrier']
            ?? $segment['validating_carrier']
            ?? $segment['provider']['supplier']
            ?? [];

        return [
            'FlightNum' => $segment['flight_number'] ?? '',
            'FlightClass' => $segment['class']['title'] ?? $segment['class']['name'] ?? '',
            'FlightMinutes' => $segment['duration']['flight']['common'] ?? $segment['ticket_duration'] ?? null,
            'FlightTime' => $this->formatDurationFromMinutes(
                (int) ($segment['duration']['flight']['common'] ?? $segment['ticket_duration'] ?? 0)
            ),
            'OperatingAirlineName' => $carrier['title'] ?? $carrier['code'] ?? '',
            'Departure' => [
                'City' => $segment['dep']['city']['title'] ?? '',
                'Airport' => $segment['dep']['airport']['title'] ?? '',
                'Code' => $segment['dep']['airport']['code'] ?? $segment['dep']['city']['code'] ?? '',
                'Date' => $this->formatMyAgentDateTime($segment['dep']['datetime'] ?? null),
            ],
            'Arrival' => [
                'City' => $segment['arr']['city']['title'] ?? '',
                'Airport' => $segment['arr']['airport']['title'] ?? '',
                'Code' => $segment['arr']['airport']['code'] ?? $segment['arr']['city']['code'] ?? '',
                'Date' => $this->formatMyAgentDateTime($segment['arr']['datetime'] ?? null),
            ],
            'Baggage' => $this->formatMyAgentBaggage($segment['baggage'] ?? null),
            'CabinBaggage' => $this->formatMyAgentBaggage($segment['cbaggage'] ?? null),
        ];
    }

    private function formatMyAgentBaggage(?array $baggage): ?string
    {
        if (!$baggage) {
            return null;
        }

        $piece = $baggage['piece'] ?? $baggage['bags_count'] ?? null;
        $weight = $baggage['weight'] ?? null;
        $unit = $baggage['weight_unit'] ?? 'KG';

        if ($piece !== null && $weight !== null) {
            return "{$piece} piece(s), {$weight} {$unit}";
        }

        if ($piece !== null) {
            return "{$piece} piece(s)";
        }

        if ($weight !== null) {
            return "{$weight} {$unit}";
        }

        return null;
    }

    private function buildPassengerName(array $passenger): string
    {
        $first = $passenger['name']['first'] ?? $passenger['firstname'] ?? '';
        $middle = $passenger['name']['middle'] ?? $passenger['middlename'] ?? '';
        $last = $passenger['name']['last'] ?? $passenger['lastname'] ?? '';

        return trim($first . ' ' . $middle . ' ' . $last) ?: 'Passenger';
    }

    private function formatContactPhone(FlightBooking $booking): ?string
    {
        $phone = $booking->contactDetail?->phone;

        if (!is_array($phone)) {
            return null;
        }

        return trim(($phone['code'] ?? '') . ($phone['number'] ?? ''));
    }

    private function formatDurationFromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('myagent')->error('MyAgent PayBookingJob failed.', [
            'booking_reference' => $this->booking->booking_reference,
            'error' => $exception->getMessage(),
        ]);
    }

    private function formatMyAgentDateTime(?string $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::createFromFormat('d.m.Y H:i:s', $value)->format('d.m.Y H:i');
        } catch (Throwable) {
            return $value;
        }
    }
}
