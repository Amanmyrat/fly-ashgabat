<?php

namespace App\Services\Nemo;

use Illuminate\Support\Facades\Log;

class SoapService
{
    public function callSoap($request, $requestTypeName)
    {
        try {
            $aviaWsdl = config('nemo.url');
            $client = new \SoapClient($aviaWsdl, array('trace' => 1));
            $result = $client->__soapCall($requestTypeName, $request);

            Log::info("XML LOG START");
            Log::info("REQUEST:\n" . $client->__getLastRequest() . "\n");
            Log::info("XML LOG END");
            Log::channel('nemo')->info('Обращение к авиа сервису Nemo выполнилось успешно');
            return $result;
        } catch (\SoapFault $exception) {
            Log::channel('nemo')->error('Ошибка обращения к авиа сервису Nemo. Причина: ' . $exception->getMessage());
            Log::critical("Class: " . __CLASS__ . ", Method: " . __METHOD__ . ", Message: " . $exception->getMessage());

            return response()->json([
                'data' => $exception->getMessage(),
                'error_type' => 'Nemo'
            ]);
        }

    }
}
