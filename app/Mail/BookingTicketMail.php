<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class BookingTicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public $tickets;
    public $bookingData;

    public function __construct(array $tickets, array $bookingData)
    {
        $this->tickets = $tickets;
        $this->bookingData = $bookingData;
    }

    public function build(): BookingTicketMail
    {
        $email = $this->subject('Вы оплатили заказ на Fly-Ashgabat!')
            ->view('emails.booking_ticket')
            ->with([
                'bookingData' => $this->bookingData,
            ]);

        // Process each ticket and attach the corresponding PDF
        foreach ($this->tickets as $ticket) {
//            $ticketUrl = $ticket['ticket_url'];
//
//            if (filter_var($ticketUrl, FILTER_VALIDATE_URL)) {
//                // If the file is a remote URL, download it temporarily
//                $tempPath = storage_path('app/temp/' . basename($ticketUrl));
//
//                // Ensure temp directory exists
//                if (!File::exists(storage_path('app/temp'))) {
//                    File::makeDirectory(storage_path('app/temp'), 0755, true);
//                }
//
//                // Download the file
//                $response = Http::get($ticketUrl);
//                if ($response->successful()) {
//                    File::put($tempPath, $response->body());
//                    $email->attach($tempPath, [
//                        'as' => basename($ticketUrl),
//                        'mime' => 'application/pdf',
//                    ]);
//                }
//            } else {
//                // If it's a local file in storage, attach it directly
//                $filePath = storage_path('app/public/' . str_replace('/storage/', '', $ticketUrl));
//                if (File::exists($filePath)) {
//                    $email->attach($filePath, [
//                        'as' => basename($filePath),
//                        'mime' => 'application/pdf',
//                    ]);
//                }
//            }

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
