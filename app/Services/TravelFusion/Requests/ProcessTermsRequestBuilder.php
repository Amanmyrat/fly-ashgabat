<?php

namespace App\Services\TravelFusion\Requests;

use DateTime;
use Exception;

class ProcessTermsRequestBuilder
{
    protected array $data;

    public function __construct(array $validatedData)
    {
        $this->data = $validatedData;
    }

    /**
     * @throws Exception
     */
    public function build(): array
    {
        return [
            'ProcessTerms' => [
                'XmlLoginId' => '', // Placeholder, will be added dynamically
                'LoginId' => '',   // Placeholder, will be added dynamically
                'RoutingId' => $this->data['routing_id'],
                'BookingProfile' => $this->buildBookingProfile(),
            ],
        ];
    }

    /**
     * @throws Exception
     */
    protected function buildBookingProfile(): array
    {
        return [
            'TravellerList' => $this->buildTravellerList(),
            'ContactDetails' => $this->buildContactDetails(),
            'BillingDetails' => $this->buildBillingDetails(),
        ];
    }

    /**
     * @throws Exception
     */
    protected function buildTravellerList(): array
    {
        $travellers = [];

        foreach ($this->data['travellers'] as $traveller) {
            $travellers[] = [
                'Age' => $this->calculateAgeCategory($traveller['birthdate']),
                'Name' => [
                    'Title' => $traveller['gender'] === 'male' ? 'Mr' : 'Ms',
                    'NamePartList' => [
                        'NamePart' => [
                            $traveller['firstname'],
                            substr($traveller['lastname'], 0, 1), // Initial of last name
                            $traveller['lastname'],
                        ],
                    ],
                ],
                'CustomSupplierParameterList' => [
                    'CustomSupplierParameter' => [
                        [
                            'Name' => 'DateOfBirth',
                            'Value' => date('d/m/Y', strtotime($traveller['birthdate'])),
                        ],
                        [
                            'Name' => 'PassportNumber',
                            'Value' => $traveller['passport_number'],
                        ],
                        [
                            'Name' => 'PassportExpiryDate',
                            'Value' => date('d/m/Y', strtotime($traveller['passport_expiry_date'])),
                        ],
                        [
                            'Name' => 'PassportCountryOfIssue',
                            'Value' => $traveller['passport_country'],
                        ],
                        [
                            'Name' => 'Nationality',
                            'Value' => $traveller['nationality'],
                        ],
                    ],
                ],
            ];
        }

        return ['Traveller' => $travellers];
    }

    protected function buildContactDetails(): array
    {
        $contactDetails = $this->data['contact_details'];

        return [
            'Name' => [
                'Title' => $contactDetails['gender'] === 'male' ? 'Mr' : 'Ms',
                'NamePartList' => [
                    'NamePart' => [
                        $contactDetails['firstname'],
                        substr($contactDetails['lastname'], 0, 1), // Initial of last name
                        $contactDetails['lastname'],
                    ],
                ],
            ],
            'Address' => [
                'Company' => '',
                'Flat' => $contactDetails['address']['flat'] ?? '',
                'BuildingName' => '',
                'BuildingNumber' => $contactDetails['address']['building_number'] ?? '',
                'Street' => $contactDetails['address']['street'],
                'Locality' => '',
                'City' => $contactDetails['address']['city'],
                'Province' => '',
                'Postcode' => '',
                'CountryCode' => $contactDetails['address']['country_code'],
            ],
            'MobilePhone' => [
                'InternationalCode' => $contactDetails['phone']['code'],
                'AreaCode' => $contactDetails['phone']['code'],
                'Number' => $contactDetails['phone']['number'],
                'Extension' => '',
            ],
            'Email' => $contactDetails['email'],
        ];
    }

    protected function buildBillingDetails(): array
    {
        return [
            'Name' => [
                'Title' => 'Mr',
                'NamePartList' => [
                    'NamePart' => ['John', 'Doe'],
                ],
            ],
            'Address' => [
                'Company' => '',
                'Flat' => '22A',
                'BuildingName' => '',
                'BuildingNumber' => '3',
                'Street' => 'George Street',
                'Locality' => '',
                'City' => 'Bristol',
                'Province' => '',
                'Postcode' => '',
                'CountryCode' => 'GB',
            ],
            'CreditCard' => [
                'Company' => '',
                'NameOnCard' => [
                    'NamePartList' => [
                        'NamePart' => ['Mr John D Doe'],
                    ],
                ],
                'Number' => '5555555555554444',
                'SecurityCode' => '887',
                'ExpiryDate' => '01/26',
                'StartDate' => '01/13',
                'CardType' => 'MasterCard',
                'IssueNumber' => '0',
            ],
        ];
    }

    /**
     * @throws Exception
     */
    protected function calculateAgeCategory(string $birthdate): int
    {
        $birthdate = new DateTime($birthdate);
        $today = new DateTime();
        $age = $today->diff($birthdate)->y;

        if ($age >= 12) {
            return 30; // Adult
        } elseif ($age >= 2) {
            return 7; // Child
        } else {
            return 0; // Infant
        }
    }

}
