<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class HotelBookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public ?string $pdfAbsolutePath = null,
    ) {}

    public function build(): self
    {
        $email = $this
            ->subject(__('Hotel booking confirmation'))
            ->view('emails.hotel_booking_confirmation')
            ->with(['booking' => $this->payload]);

        if ($this->pdfAbsolutePath !== null && is_file($this->pdfAbsolutePath)) {
            $email->attach($this->pdfAbsolutePath, [
                'as'   => 'hotel-confirmation.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $email;
    }
}
