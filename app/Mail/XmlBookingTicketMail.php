<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
class XmlBookingTicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(protected array $tickets, protected array $ticketData)
    {
    }

    public function build(): XmlBookingTicketMail
    {
        $email = $this->subject('Вы оплатили заказ на Fly-Ashgabat!')
            ->view('emails.xml_booking_ticket')
            ->with($this->ticketData);

        // Process each ticket and attach the corresponding PDF
        foreach ($this->tickets as $ticket) {
            $ticketUrl = $ticket['ticket_url'];

            $storagePath = str_replace(url('/storage'), 'storage', $ticketUrl);

            $fullFilePath = storage_path('app/public/' . str_replace('storage/', '', $storagePath));

            if (file_exists($fullFilePath)) {
                $email->attach($fullFilePath);
            } else {
                Log::error("Ticket file not found: {$fullFilePath}");
            }
        }

        return $email;
    }
}
