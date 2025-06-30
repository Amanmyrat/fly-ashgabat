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
            ],
        ];

        $flightType = Cache::get('routing_' . $this->data['routing_id']);
        if ($flightType === 'round-trip') {
            $requestData['ProcessTerms']['ReturnId'] = $this->data['return_id'];
        }

        $requestData['ProcessTerms']['BookingProfile'] = $this->buildBookingProfile();

        return $requestData;
    }

    /**
     * @throws Exception
     */
    protected function buildBookingProfile(): array
    {
        $customSupplierParameters = [
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
                'Value' => 'US',
            ],
            [
                'Name' => 'CountryOfTheUser',
                'Value' => $this->getCountryOfTheUser(),
            ],
            [
                'Name' => 'UserData',
                'Value' => $this->buildUserData(),
            ],
            [
                'Name' => 'UseTFPrepay',
                'Value' => 'Always',
            ],
        ];

        // Add general booking options to BookingProfile
        if (!empty($this->data['options'])) {
            foreach ($this->data['options'] as $optionName => $optionValue) {
                $customSupplierParameters[] = [
                    'Name' => $optionName,
                    'Value' => $optionValue,
                ];
            }
        }

        return [
            'CustomSupplierParameterList' => [
                'CustomSupplierParameter' => $customSupplierParameters,
            ],
            'TravellerList' => $this->buildTravellerList(),
            'ContactDetails' => $this->buildContactDetails(),
//            'BillingDetails' => $this->buildBillingDetails(),
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

            // Add per-passenger options if they exist for this traveller
            if (!empty($traveller['options'])) {
                foreach ($traveller['options'] as $optionName => $optionValue) {
                    $customSupplierParameters[] = [
                        'Name' => $optionName,
                        'Value' => $optionValue,
                    ];
                }
            }

            $travellers[] = [
                'Age' => $traveller['age'],
                'Name' => [
                    'Title' => $traveller['gender'] === 'male'
                        ? 'Mr'
                        : ($traveller['age'] < 18 ? 'Miss' : 'Mrs'),
                'NamePartList' => [
                        'NamePart' => array_filter([
                            $traveller['firstname'],
                            $traveller['middlename'] ?? null,
                            $traveller['lastname'],
                        ]),
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
                'Title' => $contactDetails['gender'] === 'male' ? 'Mr' : 'Mrs',
                'NamePartList' => [
                    'NamePart' => array_filter([
                        $contactDetails['firstname'],
                        $contactDetails['middlename'] ?? null,
                        $contactDetails['lastname'],
                    ]),
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
                'AreaCode' => '',
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

    protected function getRequestOrigin(): string
    {
        // Use business market/point of sale (US) + domain as per TravelFusion definition
        return 'US-flyashgabat.com';
    }

    protected function getCountryOfTheUser(): string
    {
        $ip = $this->data['meta']['end_user_ip_address'] ?? '';
        return $this->ipGeolocationService->getCountryCode($ip);
    }

}
