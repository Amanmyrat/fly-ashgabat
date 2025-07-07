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
            $tariffsData = $preBookResponse['Tariffs'];
            
            if(isset($tariffsData['ServiceInfo'])){
                foreach($tariffsData['ServiceInfo'] as $tariff){
                    $features = [];
                    
                    // Parse the text to extract features
                    if(isset($tariff['Text']['value'])){
                        $textLines = explode("\n", trim($tariff['Text']['value']));
                        
                        foreach($textLines as $line){
                            $line = trim($line);
                            if(empty($line)) continue;
                            
                            $enabled = true;
                            $withCharge = false;
                            $text = '';
                            
                            if(str_starts_with($line, '+')){
                                // Included feature
                                $enabled = true;
                                $withCharge = false;
                                $text = trim(substr($line, 1));
                            } elseif(str_starts_with($line, '-')){
                                // Not included feature
                                $enabled = false;
                                $withCharge = false;
                                $text = trim(substr($line, 1));
                            } elseif(str_starts_with($line, '!')){
                                // Feature with charge
                                $enabled = true;
                                $withCharge = true;
                                $text = trim(substr($line, 1));
                            }
                            
                            if(!empty($text)){
                                $features[] = [
                                    'text' => $text,
                                    'enabled' => $enabled,
                                    'withCharge' => $withCharge
                                ];
                            }
                        }
                    }
                    
                    $tariffs[] = [
                        'id' => $tariff['Id']['value'] ?? null,
                        'name' => $tariff['Name']['value'] ?? null,
                        'price' => isset($tariff['Price']['value']) ? (float)$tariff['Price']['value'] : 0,
                        'features' => $features
                    ];
                }
            }
        }

        return [
            'success' => true,
            'data' => $tariffs
        ];

    }

}
