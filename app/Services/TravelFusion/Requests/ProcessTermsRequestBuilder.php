<?php

namespace App\Services\TravelFusion\Requests;

use DateTime;
use Exception;
use Illuminate\Support\Facades\Cache;
use App\Services\IpGeolocationService;

class ProcessTermsRequestBuilder
{
    protected array $data;
    private IpGeolocationService $ipGeolocationService;

    public function __construct(
        array $validatedData,
        IpGeolocationService $ipGeolocationService
    ) {
        $this->data = $validatedData;
        $this->ipGeolocationService = $ipGeolocationService;
    }

    /**
     * @throws Exception
     */
    public function build(): array
    {
        $requestData = [
            'ProcessTerms' => [
                'XmlLoginId' => '', // Placeholder, will be added dynamically
                'LoginId' => '',   // Placeholder, will be added dynamically
                'RoutingId' => $this->data['routing_id'],
                'OutwardId' => $this->data['outward_id'],
                'BookingProfile' => $this->buildBookingProfile(),
            ],
        ];

        $flightType = Cache::get('routing_' . $this->data['routing_id']);
        if ($flightType === 'round-trip') {
            $requestData['ProcessTerms']['ReturnId'] = $this->data['return_id'];
        }

        return $requestData;
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
            'CustomSupplierParameterList' => [
                'CustomSupplierParameter' => [
                    [
                        'Name' => 'EndUserIPAddress',
                        'Value' => $this->data['meta']['end_user_ip_address'] ?? '',
                    ],
                    [
                        'Name' => 'EndUserBrowserAgent',
                        'Value' => $this->data['meta']['end_user_browser_agent'] ?? '',
                    ],
                    [
                        'Name' => 'EndUserDeviceMACAddress',
                        'Value' => $this->data['meta']['end_user_device_mac_address'] ?? '',
                    ],
                    [
                        'Name' => 'RequestOrigin',
                        'Value' => $this->getRequestOrigin(),
                    ],
                    [
                        'Name' => 'PointOfSale',
                        'Value' => 'TM',
                    ],
                    [
                        'Name' => 'CountryOfTheUser',
                        'Value' => $this->getCountryOfTheUser(),
                    ],
                    [
                        'Name' => 'UserData',
                        'Value' => $this->buildUserData(),
                    ],
                ],
            ],
        ];
    }

    protected function buildUserData(): string
    {
        $userData = [];
        
        // Add email
        if (!empty($this->data['contact_details']['email'])) {
            $userData[] = str_replace(',', '\,', $this->data['contact_details']['email']);
        }
        
        // Add phone number
        if (!empty($this->data['contact_details']['phone'])) {
            $phone = $this->data['contact_details']['phone'];
            $phoneNumber = '+' . ($phone['code'] ?? '') . ($phone['number'] ?? '');
            $userData[] = str_replace(',', '\,', $phoneNumber);
        }
        
        // Add name from contact details
        if (!empty($this->data['contact_details']['firstname']) && !empty($this->data['contact_details']['lastname'])) {
            $name = $this->data['contact_details']['firstname'] . ' ' . $this->data['contact_details']['lastname'];
            $userData[] = str_replace(',', '\,', $name);
        }
        
        return implode(',', $userData);
    }

    /**
     * @throws Exception
     */
    protected function buildTravellerList(): array
    {
        $travellers = [];

        foreach ($this->data['travellers'] as $traveller) {
            $customSupplierParameters = [
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
            ];

            // Loop through options and add them to CustomSupplierParameterList
            if (!empty($this->data['options'])) {
                foreach ($this->data['options'] as $optionName => $optionValue) {
                    $customSupplierParameters[] = [
                        'Name' => $optionName,
                        'Value' => $optionValue,
                    ];
                }
            }

            $travellers[] = [
                'Age' => $this->calculateAgeCategory($traveller['birthdate']),
                'Name' => [
                    'Title' => $traveller['gender'] === 'male' ? 'Mr' : 'Ms',
                    'NamePartList' => [
                        'NamePart' => [
                            $traveller['firstname'],
                            $traveller['lastname'],
                        ],
                    ],
                ],
                'CustomSupplierParameterList' => [
                    'CustomSupplierParameter' => $customSupplierParameters,
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
                        $contactDetails['lastname'],
                    ],
                ],
            ],
            'Address' => [
                'Company' => '',
                'Flat' => '',
                'BuildingName' => '',
                'BuildingNumber' =>  '',
                'Street' => $contactDetails['address']['street'],
                'Locality' => '',
                'City' => $contactDetails['address']['city'],
                'Province' => 'OT',
                'Postcode' => 'NONE',
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
                'Province' => 'OT',
                'Postcode' => 'NONE',
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

    protected function getRequestOrigin(): string
    {
        $ip = $this->data['meta']['end_user_ip_address'] ?? '';
        $country = $this->getCountryOfTheUser();
        return sprintf('%s-flyashgabat.com', $country);
    }

    protected function getCountryOfTheUser(): string
    {
        $ip = $this->data['meta']['end_user_ip_address'] ?? '';
        return $this->ipGeolocationService->getCountryCode($ip);
    }

}
