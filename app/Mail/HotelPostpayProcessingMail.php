<?php

namespace App\Mail;

use App\Models\HotelBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class HotelPostpayProcessingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public HotelBooking $booking,
    ) {}

    public function build(): self
    {
        return $this
            ->subject(__('Hotel booking processed - Admin confirmation required'))
            ->view('emails.hotel_postpay_processing')
            ->with(['booking' => $this->booking]);
    }
}
