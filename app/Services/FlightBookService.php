<?php

namespace App\Services;

use App\Mail\BookingTicketMail;
use App\Services\TravelFusion\Requests\CheckBookingRequestBuilder;
use App\Services\TravelFusion\Requests\GetBookingRequestBuilder;
use App\Services\TravelFusion\Requests\ProcessDetailsRequestBuilder;
use App\Services\TravelFusion\Requests\ProcessTermsRequestBuilder;
use App\Services\TravelFusion\Requests\StartBookingRequestBuilder;
use App\Services\TravelFusion\TravelFusionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FlightBookService
{

    public function __construct(
        protected TravelFusionService $travelFusionService,
    )
    {
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function book(array $validatedData): array
    {
        // Step 1: ProcessDetails
        // TODO add baggage and luggage options to request
        $processDetailsRequest = (new ProcessDetailsRequestBuilder($validatedData))->build();
        $processDetailsResponse = $this->travelFusionService->sendRequest($processDetailsRequest);

        if (!isset($processDetailsResponse['ProcessDetails']['Router']['GroupList']['Group'])) {
            return [
                'success' => false,
                'message' => 'No result(ProcessDetails) found'
            ];
        }

        // Step 2: ProcessTerms
        $processTermsRequest = (new ProcessTermsRequestBuilder($validatedData))->build();
        $processTermsResponse = $this->travelFusionService->sendRequest($processTermsRequest, 'processTerms');

        if (!isset($processTermsResponse['ProcessTerms']['Router']['GroupList']['Group'])) {
            return [
                'success' => false,
                'message' => 'No result(ProcessTerms) found'
            ];
        }

        $data = [
            'tf_booking_reference' => $processTermsResponse['ProcessTerms']['TFBookingReference'],
            'price' => $processTermsResponse['ProcessTerms']['Router']['GroupList']['Group']['Price']
        ];

        // Step 3: StartBooking
        $startBookingRequest = (new StartBookingRequestBuilder($data))->build();
        $startBookingResponse = $this->travelFusionService->sendRequest($startBookingRequest);

        if (!isset($startBookingResponse['StartBooking']['TFBookingReference'])) {
            return [
                'success' => false,
                'message' => 'No result(StartBooking) found'
            ];
        }

        return [
            'success' => true,
            'book_id' => $startBookingResponse['StartBooking']['TFBookingReference'],
            'message' => 'Booking successful',
        ];
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function checkBooking(string $bookId): array
    {
        $checkBookingRequest = (new CheckBookingRequestBuilder($bookId))->build();
        $checkBookingResponse = $this->travelFusionService->sendRequest($checkBookingRequest);

        if (!isset($checkBookingResponse['CheckBooking'])) {
            return [
                'success' => false,
                'message' => 'No result(CheckBooking) found'
            ];
        }
        return [
            'success' => true,
            'status' => $checkBookingResponse['CheckBooking']['Status'],
        ];

    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function bookingDetails(string $bookId): array
    {
        $getBookingRequest = (new GetBookingRequestBuilder($bookId))->build();
        $getBookingResponse = $this->travelFusionService->sendRequest($getBookingRequest);

        if (!isset($getBookingResponse['GetBookingDetails'])) {
            return [
                'success' => false,
                'message' => 'No result(GetBookingDetails) found'
            ];
        }

        $bookingDetails = $getBookingResponse['GetBookingDetails'];

        $travelersList = $bookingDetails['BookingProfile']['TravellerList']['Traveller'] ?? [];

        $travelers = is_array($travelersList) && array_keys($travelersList) !== range(0, count($travelersList) - 1)
            ? [$travelersList]
            : $travelersList;

        unset($bookingDetails['RouterHistory']['BookingRouter']['RequiredParameterList']);
        $bookingData = $bookingDetails['RouterHistory']['BookingRouter'];
        $supplierReference = $bookingDetails['SupplierReference'];

        $contactDetails = $bookingDetails['BookingProfile']['ContactDetails'];
        $contactData = [
           'email' => $contactDetails['Email'],
//           'email' => 'tekemuradov@gmail.com',
           'phone' => $contactDetails['MobilePhone']['InternationalCode'].$contactDetails['MobilePhone']['Number'],
        ];

        // Process travelers and generate tickets
        $tickets = array_map(function ($traveler) use ($bookingData, $supplierReference, $contactData) {
            return $this->processTraveler($traveler, $bookingData, $supplierReference, $contactData);
        }, $travelers);

        Mail::to($contactData['email'])->send(new BookingTicketMail($tickets,  $bookingData));

        return [
            'success' => true,
            'tickets' => $tickets
        ];

    }

    private function processTraveler(array $traveler, array $bookingData, string $supplierReference, array $contactData): array
    {
        $nameParts = $traveler['Name']['NamePartList']['NamePart'] ?? [];
        $fullName = implode(' ', $nameParts);

        // Define the ticket path inside 'storage/app/public/tickets/'
        $ticketPath = 'tickets/' . Str::slug($fullName) . '__' . now()->getTimestamp() . '.pdf';

        // Prepare data for the PDF
        $data = compact('traveler', 'bookingData', 'supplierReference', 'contactData');

        // Generate the PDF using DomPDF
        $pdf = SnappyPdf::loadView('pdf.ticket', $data)
            ->setOption('encoding', 'UTF-8');

        // Get the PDF content and store it in the public disk
        Storage::disk('public')->put($ticketPath, $pdf->download()->getOriginalContent());

        // Return the full URL to the stored PDF
        $ticketUrl =  Storage::disk('public')->url($ticketPath);

        return [
            'name' => $fullName,
            'ticket_url' => $ticketUrl
        ];
    }


}
