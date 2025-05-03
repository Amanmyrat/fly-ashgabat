<?php

namespace App\Services\TravelFusion\Requests;

use Illuminate\Support\Facades\Cache;

class ProcessDetailsRequestBuilder
{
    protected array $data;

    public function __construct(array $validatedData)
    {
        $this->data = $validatedData;
    }

    public function build(): array
    {
        $requestData = [
            'ProcessDetails' => [
                'XmlLoginId' => '', // Placeholder, will be added dynamically
                'LoginId' => '',   // Placeholder, will be added dynamically
                'RoutingId' => $this->data['routing_id'],
                'OutwardId' => $this->data['outward_id'],
                'HandoffParametersOnly' => 'false',
            ],
        ];

        $flightType = Cache::get('routing_' . $this->data['routing_id']);
        if ($flightType === 'round-trip') {
            $requestData['ProcessDetails']['ReturnId'] = $this->data['return_id'];
        }

        return $requestData;
    }

}
