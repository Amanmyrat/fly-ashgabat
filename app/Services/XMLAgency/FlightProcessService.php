<?php

namespace App\Services\XMLAgency;

use App\Enum\BookingStatus;
use App\Enum\FlightSupplier;
use App\Http\Requests\XMLAgency\PreBookRequestBuilder;
use App\Models\FlightBooking;
use App\Models\User;
use App\Http\Requests\XMLAgency\AeroBookRequestBuilder;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

class FlightProcessService
{
    public function __construct(
        protected XMLAgencyService $xmlAgencyService
    ) {
    }

    /**
     * Process XMLAgency booking
     *
     * @throws ConnectionException
     * @throws Exception
     */
    public function processFlight(array $validatedData): array
    {
        $preBookRequest = (new PreBookRequestBuilder($validatedData))->build();
        $preBookResponse = $this->xmlAgencyService->sendRequest($preBookRequest, 'AeroPrebook');

        if ($preBookResponse['Success']['value'] != "true") {
            $errorMessage = $preBookResponse['AeroPrebookResult']['ErrorString'] ?? 'Process flight failed';
            return [
                'success' => false,
                'message' => $errorMessage,
                'data' => $preBookResponse
            ];
        }

        $tariffs = [];

        if(isset($preBookResponse['Tariffs'])){
            $tariffs = $preBookResponse['Tariffs'];

            dd($tariffs);
        }

        return [
            'success' => true,
            'data' => $tariffs
        ];

    }

}
