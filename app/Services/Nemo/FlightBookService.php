<?php

namespace App\Services\Nemo;

use App\Enum\BookingStatus;
use App\Enum\FlightSupplier;
use App\Enum\PaymentType;
use App\Models\FlightBooking;
use App\Models\User;
use App\Services\Nemo\RequestGenerate\AdditionalOperationsRequestGenerateService;
use App\Services\Nemo\RequestGenerate\BookFlightRequestGenerateService;
use App\Services\Nemo\RequestGenerate\UpdateBookRequestGenerateService;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Log;

class FlightBookService
{

    public function __construct(
        protected SoapService     $soapService,
        protected BookFlightRequestGenerateService $bookFlightRequestGenerateService,
        protected AdditionalOperationsRequestGenerateService $additionalOperationsRequestGenerateService,
        protected UpdateBookRequestGenerateService $updateBookRequestGenerateService,
    )
    {
    }

    /**
     * @throws \Exception
     */
    public function processBooking(array $requestData, ?User $user): array
    {
        $validationResult = $this->validateExistingBookings($requestData);

        if (!$validationResult['success']) {
            return $validationResult;
        }

        $validatedData = $requestData;

        $generatedRequest = $this->bookFlightRequestGenerateService->generateBookFlightRequest($requestData);

        $result = $this->soapService->callSoap($generatedRequest, 'BookFlight_2_2');

        if (isset($result->BookFlight_2_2Result->Errors) || isset($result->Errors)) {
            $this->logErrors($result);
            return $this->handleBookingErrors($result);
        }

        $result = json_decode(json_encode($result), true);

        $responseBody = $result['BookFlight_2_2Result']['ResponseBody'];

        $actualPrice = $responseBody['Price']['TotalPrice'];

        if ($validatedData['payment_type'] === PaymentType::BALANCE->value && (!$user || $user->balance < $actualPrice['Amount'])) {
            return ['success' => false, 'message' => 'Insufficient balance.', 'balance' => $user->balance, 'price' => $actualPrice ['Amount']];
        }

        $services = $responseBody['Services']['Service'];

        if (isset($services['ID'])) {
            $services = [$services];
        }

        $allSegments = [];
        foreach ($services as $service) {
            if (isset($service['Segments']['FlightSegment'])) {
                $segments = $service['Segments']['FlightSegment'];

                if (isset($segments['ID'])) {
                    $segmentsToProcess = [$segments];
                } else {
                    $segmentsToProcess = $segments;
                }

                $allSegments = array_merge($allSegments, $segmentsToProcess);
            }
        }

        $outwardSegments = [];
        $returnSegments = [];

        foreach ($allSegments as $segment) {
            if ($segment['RequestedSegment'] == 0) {
                $outwardSegments[] = $segment;
            } else if ($segment['RequestedSegment'] == 1) {
                $returnSegments[] = $segment;
            }
        }

        $origin = ['Code' => 'Unknown'];
        $destination = ['Code' => 'Unknown'];

        if (!empty($outwardSegments)) {
            $origin = [
                'Code' => $outwardSegments[0]['DepatureAirport']['Code'],
                'City' => $outwardSegments[0]['DepatureAirport']['CityCode'] ?? ''
            ];
            $lastOutwardSegment = end($outwardSegments);
            $destination = [
                'Code' => $lastOutwardSegment['ArrivalAirport']['Code'],
                'City' => $lastOutwardSegment['ArrivalAirport']['CityCode'] ?? ''
            ];
        }

        $outwardData = null;
        $returnData = null;

        if (!empty($outwardSegments)) {
            $outwardData = $this->buildJourneyData($outwardSegments);
        }

        if (!empty($returnSegments)) {
            $returnData = $this->buildJourneyData($returnSegments);
        }

        $bookingData = [
            'user_id' => $user?->id ?? null,
            'booking_reference' => $responseBody['ID'],
            'supplier_reference' => $responseBody['OwnerID'],
            'flight_type' => FlightSupplier::Nemo,
            'origin' => $origin,
            'destination' => $destination,
            'outward' => $outwardData,
            'return' => $returnData,
            'price' => $actualPrice,
            'payment_type' => $validatedData['payment_type'],
            'status' => BookingStatus::PENDING->value,
        ];

        $booking = FlightBooking::create($bookingData);

        if (!$booking) {
            throw new \Exception('Failed to create booking');
        }

        if (!empty($validatedData['contact_details'])) {
            $contactData = [
                'email' => $validatedData['contact_details']['email'],
                'phone' => $validatedData['contact_details']['phone'],
                'gender' => null,
                'firstname' => null,
                'lastname' => null,
                'address' => null,
            ];
            $booking->contactDetail()->create($contactData);
        }

        if (!empty($validatedData['travellers'])) {
            $travellersData = [];
            foreach ($validatedData['travellers'] as $traveller) {
                $travellersData[] = [
                    'birthdate' => $traveller['birthdate'],
                    'passport_number' => $traveller['passport_number'],
                    'nationality' => $traveller['nationality'],
                    'firstname' => $traveller['firstname'],
                    'lastname' => $traveller['lastname'],
                    'middlename' =>  null,
                    'gender' => $traveller['gender'],
                    'passport_expiry_date' => $traveller['passport_expiry_date'],
                    'passport_country' => $traveller['passport_country'],
                ];
            }
            $booking->travellers()->createMany($travellersData);
        }

        Log::info('Nemo booking created', [
            'booking_reference' => $booking->booking_reference,
            'supplier_reference' => $booking->supplier_reference,
            'price' => $actualPrice
        ]);

        $this->updateBook($responseBody['ID']);

        return [
            'success' => true,
            'booking' => $booking,
        ];
    }

    /**
     * Build journey data from segments (similar to XMLAgency FlightBookService)
     */
    private function buildJourneyData(array $segments): array
    {
        if (empty($segments)) {
            return [];
        }

        $firstSegment = $segments[0];
        $lastSegment = end($segments);

        $departureDateTime = Carbon::createFromFormat('Y-m-d\TH:i:s', $firstSegment['DepatureDateTime']);
        $arrivalDateTime = Carbon::createFromFormat('Y-m-d\TH:i:s', $lastSegment['ArrivalDateTime']);

        $totalMinutes = $departureDateTime->diffInMinutes($arrivalDateTime);
        $totalHours = intval($totalMinutes / 60);
        $remainingMinutes = $totalMinutes % 60;

        $stops = [];
        $stopsCount = count($segments) - 1;

        for ($i = 0; $i < count($segments) - 1; $i++) {
            $currentSegment = $segments[$i];
            $nextSegment = $segments[$i + 1];

            $arrivalTime = Carbon::createFromFormat('Y-m-d\TH:i:s', $currentSegment['ArrivalDateTime']);
            $departureTime = Carbon::createFromFormat('Y-m-d\TH:i:s', $nextSegment['DepatureDateTime']);

            $layoverDuration = $arrivalTime->diffInMinutes($departureTime);

            if ($layoverDuration < 0) {
                $layoverDuration = 0;
            }

            $layoverHours = intval($layoverDuration / 60);
            $layoverMinutesRemainder = $layoverDuration % 60;

            $stopAirportCode = $currentSegment['ArrivalAirport']['Code'];
            $stopAirportInfo = [
                'Code' => $stopAirportCode,
                'Name' => $currentSegment['ArrivalAirport']['Code'] ?? '',
                'City' => $currentSegment['ArrivalAirport']['CityCode'] ?? ''
            ];

            $stops[] = [
                'Location' => $stopAirportInfo,
                'Duration' => [
                    'Hours' => $layoverHours,
                    'Minutes' => $layoverMinutesRemainder
                ]
            ];
        }

        $segmentsData = [];
        foreach ($segments as $segment) {
            $airlineCode = $segment['MarketingAirline'];
            $airlineInfo = [
                'Code' => $airlineCode,
            ];

            $segmentsData[] = [
                'FlightNumber' => $segment['FlightNumber'],
                'Airline' => $airlineInfo,
                'Aircraft' => $segment['AircraftType'],
                'Departure' => [
                    'Code' => $segment['DepatureAirport']['Code'],
                    'Date' => $segment['DepatureDateTime']
                ],
                'Arrival' => [
                    'Code' => $segment['ArrivalAirport']['Code'],
                    'Date' => $segment['ArrivalDateTime']
                ],
                'Duration' => $segment['FlightTime'],
                'Class' => $segment['BookingClassCode'],
            ];
        }

        return [
            'Duration' => [
                'Hours' => $totalHours,
                'Minutes' => $remainingMinutes
            ],
            'DepartDate' => [
                'Date' => $departureDateTime->format('d/m/Y'),
                'Time' => $departureDateTime->format('H:i')
            ],
            'ArriveDate' => [
                'Date' => $arrivalDateTime->format('d/m/Y'),
                'Time' => $arrivalDateTime->format('H:i')
            ],
            'Stops' => $stops,
            'StopsCount' => $stopsCount,
            'Segments' => $segmentsData
        ];
    }

    protected function validateExistingBookings(array $request): array
    {
        $actualizeFlightRequest = [
            'flight_id' => $request['flight_id'],
            'operation' => 'ActualizeFlight',
        ];

        $generatedRequest = $this->additionalOperationsRequestGenerateService->generateAdditionalOperationsRequest($actualizeFlightRequest);
        $actualizeFlightResult = $this->soapService->callSoap($generatedRequest, 'AdditionalOperations_1_2');

        if (isset($actualizeFlightResult->AdditionalOperations_1_2Result->Errors) || isset($actualizeFlightResult->Errors)) {
            return [
                'success' => false,
                'status_code' => 500,
                'message' => 'Во время обработки запроса произошла ошибка. Попробуйте позже.'
            ];
        }

        $actualizedFlightNumbers = [];
        $segments = $actualizeFlightResult->AdditionalOperations_1_2Result->ResponseBody->ActualizedFlight->Segments->Segment;
        $segments = is_array($segments) ? $segments : [$segments];
        foreach ($segments as $segment) {
            $flightNumber = "$segment->OpAirline-$segment->FlightNumber";
            $actualizedFlightNumbers[] = $flightNumber;
        }

        // Check each traveller for existing bookings
        foreach ($request['travellers'] as $traveller) {
            $formattedBirthdate = DateTime::createFromFormat('d.m.Y', $traveller['birthdate']) ?: DateTime::createFromFormat('Y-m-d', $traveller['birthdate']);
            $formattedBirthdate = $formattedBirthdate?->format('Y-m-d');

            $formattedExpiryDate = DateTime::createFromFormat('d.m.Y', $traveller['passport_expiry_date']) ?: DateTime::createFromFormat('Y-m-d', $traveller['passport_expiry_date']);
            $formattedExpiryDate = $formattedExpiryDate?->format('Y-m-d');

            $existingBookings = FlightBooking::where('status', BookingStatus::PENDING->value)
                ->whereHas('travellers', function ($query) use ($traveller, $formattedBirthdate, $formattedExpiryDate) {
                    $query->where('firstname', $traveller['firstname'])
                        ->where('lastname', $traveller['lastname'])
                        ->where('passport_number', $traveller['passport_number'])
                        ->where('nationality', $traveller['nationality'])
                        ->where('birthdate', $formattedBirthdate)
                        ->where('passport_expiry_date', $formattedExpiryDate);
                })
                ->get();

            foreach ($existingBookings as $existingBooking) {
                $bookingFlightNumbers = [];
                $hasAeroflotSegment = false;

                // Extract flight segments from outward and return journeys
                $allSegments = [];

                if ($existingBooking->outward && isset($existingBooking->outward['Segments'])) {
                    $allSegments = array_merge($allSegments, $existingBooking->outward['Segments']);
                }

                if ($existingBooking->return && isset($existingBooking->return['Segments'])) {
                    $allSegments = array_merge($allSegments, $existingBooking->return['Segments']);
                }

                foreach ($allSegments as $segment) {
                    $airline = $segment['Airline']['Code'] ?? '';
                    $flightNumber = $segment['FlightNumber'] ?? '';
                    $bookingFlightNumbers[] = "{$airline}-{$flightNumber}";

                    if ($airline === 'SU') {
                        $hasAeroflotSegment = true;
                    }
                }

                $matchingFlights = array_intersect($actualizedFlightNumbers, $bookingFlightNumbers);

                if (!empty($matchingFlights) && $hasAeroflotSegment) {
                    return [
                        'error' => true,
                        'status_code' => 409,
                        'message' => 'Aeroflot restriction: One or more travelers have a booking that cannot be rebooked due to Aeroflot restrictions.'
                    ];
                }

                if (!empty($matchingFlights)) {
                    $bookingAge = now()->diffInHours($existingBooking->created_at);
                    $maxBookingHours = 24;

                    if ($bookingAge < $maxBookingHours) {
                        return [
                            'error' => true,
                            'status_code' => 409,
                            'message' => 'One or more travelers have an unpaid booking that hasn\'t expired yet.'
                        ];
                    }
                }
            }
        }

        return ['success' => true];
    }

    private function logErrors($result): void
    {
        $errors = is_array($result->BookFlight_2_2Result->Errors->Error) ?
            $result->BookFlight_2_2Result->Errors->Error :
            [$result->BookFlight_2_2Result->Errors->Error];

        foreach ($errors as $error) {
            Log::alert('Error in BookFlight: Level ' . $error->Level . ' Code ' . $error->Code . ' Message ' . $error->Message);
            Log::channel('nemo')->error("Ответ от Nemo.API: Уровень - $error->Level, Код ошибки - $error->Code, Сообщение - $error->Message, Class - " . __CLASS__ . ", Function: " . __METHOD__);
        }
    }

    public function updateBook(int $bookingId): bool
    {
        Log::channel('nemo')->info('Синхронизация брони у поставщика для букинга: ' . $bookingId);
        $generatedRequest = $this->updateBookRequestGenerateService->generateUpdateBookRequest($bookingId);

        Log::channel('nemo')->info('Запрос: ');
        Log::channel('nemo')->info(json_encode($generatedRequest, JSON_UNESCAPED_UNICODE));

        $result = $this->soapService->callSoap($generatedRequest, 'UpdateBook_2_2');

        Log::channel('nemo')->info('Ответ: ');
        Log::channel('nemo')->info(json_encode($result, JSON_UNESCAPED_UNICODE));

        if (isset($result->UpdateBook_2_2Result->Errors)) {
            $this->logUpdateBookErrors($result, $bookingId);
            return false;
        }

        if (isset($result->UpdateBook_2_2Result->ResponseBody->ID)) {
            Log::channel('nemo')->info('Синхронизация брони у поставщика для букинга: ' . $bookingId . ' прошла успешно');
            return true;
        }

        Log::channel('nemo')->info('Синхронизация брони у поставщика для букинга: ' . $bookingId . ' не удалась');
        return false;
    }

    private function logUpdateBookErrors($result, int $bookingId): void
    {
        $errors = is_array($result->UpdateBook_2_2Result->Errors->Error) ?
            $result->UpdateBook_2_2Result->Errors->Error :
            [$result->UpdateBook_2_2Result->Errors->Error];

        foreach ($errors as $error) {
            Log::alert('Error in UpdateBook: Booking ID ' . $bookingId . ' Level ' . $error->Level . ' Code ' . $error->Code . ' Message ' . $error->Message);
            Log::channel('nemo')->error('Error in UpdateBook: Booking ID ' . $bookingId . ' Level ' . $error->Level . ', Error code: ' . $error->Code . ', Error message: ' . $error->Message . ', Class: ' . __CLASS__ . ', Function: ' . __FUNCTION__ . ', Line: ' . __LINE__);

        }
    }

    private function handleBookingErrors($result): array
    {
        $errors = $result->BookFlight_2_2Result->Errors->Error ?? $result->Errors->Error;

        $errors = is_array($errors) ? $errors : [$errors];

        $firstError = $errors[0];
        $level = $firstError->Level ?? '';

        return match ($level) {
            'APIFormat' => [
                'success' => false,
                'status_code' => 400,
                'message' => 'Некорректные параметры запроса. Проверьте введённые данные.'
            ],
            'Supplier' => [
                'success' => false,
                'status_code' => 409,
                'message' => 'Мы хотим показать вам только актуальные билеты.'
            ],
            'Network' => [
                'success' => false,
                'status_code' => 503,
                'message' => 'Сервис временно недоступен. Попробуйте позже.'
            ],
            default => [
                'error' => false,
                'status_code' => 500,
                'message' => 'Во время обработки запроса произошла ошибка. Попробуйте позже.'
            ],
        };
    }
}
