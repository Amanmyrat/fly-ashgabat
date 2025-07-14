<?php

namespace App\Services\Nemo\RequestGenerate;

use JetBrains\PhpStorm\ArrayShape;
use Carbon\Carbon;

class BookFlightRequestGenerateService
{
    /**
     * Generate a book flight request based on the provided input data.
     *
     * @param array $postRequest The input data for the book flight request.
     *
     * @return array The generated book flight request.
     */
    #[ArrayShape(['BookFlight_2_2' => "array[]"])]
    public function generateBookFlightRequest(array $postRequest): array
    {
        $bookFlightRequest = $this->initializeRequest();

        $bookFlightRequest['BookFlight_2_2']['Request']['RequestBody']['FlightID'] = $postRequest['flight_id'];

        $this->populateTravellers($bookFlightRequest, $postRequest['travellers']);
        $this->populateContactDetails($bookFlightRequest, $postRequest['contact_details']);

        $this->finalizeRequest($bookFlightRequest);

        return $bookFlightRequest;
    }

    /**
     * Initialize the book flight request structure.
     *
     * @return array The initialized book flight request.
     */
    #[ArrayShape(['BookFlight_2_2' => "array[]"])]
    private function initializeRequest(): array
    {
        return [
            'BookFlight_2_2' => [
                'Request' => [
                    'Requisites' => [
                        'AuthToken' => config('nemo.auth_token'),
                    ],
                    'UserID' => config('nemo.user_id'),
                    'RequestBody' => []
                ]
            ]
        ];
    }

    /**
     * Populate the book flight request with traveler information.
     *
     * @param array  $request   The book flight request to update.
     * @param array  $travellers The traveler information to populate.
     */
    private function populateTravellers(array &$request, array $travellers): void
    {
        // Store original travellers data for passport information access
        $this->originalTravellers = $travellers;
        
        $travellerIndex = 1;
        
        foreach ($travellers as $traveller) {
            $passengerType = $this->determinePassengerType($traveller['birthdate']);
            
            $request['BookFlight_2_2']['Request']['RequestBody']['Travellers'][] = [
                'ID' => $travellerIndex,
                'Type' => $passengerType,
                'Name' => $traveller['firstname'],
                'LastName' => $traveller['lastname'],
                'DateOfBirth' => Carbon::parse($traveller['birthdate'])->format('d.m.Y'),
                'Nationality' => $traveller['nationality'],
                'Gender' => $traveller['gender'] === 'male' ? 'M' : 'F',
            ];
            
            $travellerIndex++;
        }
    }

    /**
     * Populate the book flight request with contact details as data items.
     *
     * @param array  $request        The book flight request to update.
     * @param array  $contactDetails The contact details to populate.
     */
    private function populateContactDetails(array &$request, array $contactDetails): void
    {
        $dataItemIndex = 1;
        
        // Add contact info data item
        $request['BookFlight_2_2']['Request']['RequestBody']['DataItems'][] = [
            'ID' => $dataItemIndex,
            'Type' => 'ContactInfo',
            'TravellerRef' => ['Ref' => 1], // Reference to first traveller
            'ContactInfo' => [
                'EmailID' => $contactDetails['email'],
                'Telephone' => [
                    'Type' => 'M', // Mobile
                    'PhoneNumber' => $contactDetails['phone']['code'] . $contactDetails['phone']['number'],
                ]
            ]
        ];
        
        $dataItemIndex++;
        
        // Add passport document data items for each traveller
        $travellerIndex = 1;
        
        foreach ($this->originalTravellers as $traveller) {
            $request['BookFlight_2_2']['Request']['RequestBody']['DataItems'][] = [
                'ID' => $dataItemIndex,
                'Type' => 'IDDocument',
                'TravellerRef' => ['Ref' => $travellerIndex],
                'Document' => [
                    'Type' => 'InternationalPassport', // International passport for international travel
                    'Number' => $traveller['passport_number'],
                    'IssueCountryCode' => $traveller['passport_country'],
                    'ElapsedTime' => Carbon::parse($traveller['passport_expiry_date'])->format('d.m.Y'),
                ]
            ];
            
            $dataItemIndex++;
            $travellerIndex++;
        }
    }

    /**
     * Determine passenger type based on birthdate.
     *
     * @param string $birthdate The birthdate of the passenger.
     *
     * @return string The passenger type (ADT, CNN, INF).
     */
    private function determinePassengerType(string $birthdate): string
    {
        $age = Carbon::parse($birthdate)->age;
        
        if ($age < 2) {
            return 'INF'; // Infant - under 2 years old
        } elseif ($age < 12) {
            return 'CNN'; // Child - 2 to under 12 years old
        } else {
            return 'ADT'; // Adult - 12 years old and above
        }
    }

    /**
     * Store original travellers data for passport information access.
     */
    private array $originalTravellers = [];

    /**
     * Finalize the book flight request by adding remaining information.
     *
     * @param array $request The book flight request to update.
     */
    private function finalizeRequest(array &$request): void
    {
        $request['BookFlight_2_2']['Request']['RequestBody']['RequestorTags'] = config('nemo.tags');
    }
}
