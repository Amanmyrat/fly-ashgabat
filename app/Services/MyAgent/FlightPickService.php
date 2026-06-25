<?php

namespace App\Services\MyAgent;

use App\Services\MyAgent\RequestBuilder\FlightDetailsRequestBuilder;
use App\Support\MyAgentFlightPickCache;
use Exception;
use Illuminate\Support\Facades\Cache;

class FlightPickService
{
    public function __construct(
        protected MyAgentService $myAgentService
    ) {
    }

    /**
     * @throws Exception
     */
    public function pick(array $validatedData): array
    {
        $flightId = $validatedData['id'];
        $builder = new FlightDetailsRequestBuilder($validatedData);

        $response = $this->myAgentService->get(
            '/avia/flight-info',
            $builder->buildFlightInfoQuery()
        );

        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            throw new Exception('MyAgent flight-info response did not contain flight data.');
        }

        $flight = $this->extractFlight($data);

        if (!is_array($flight)) {
            throw new Exception('MyAgent flight-info response did not contain flight data.');
        }

        $resolvedFlightId = (string) ($flight['id'] ?? $flightId);
        $checks = $this->extractBookingChecks($data, $flight);
        $bookingCache = $this->buildBookingCache($resolvedFlightId, $checks);

        MyAgentFlightPickCache::put($resolvedFlightId, $bookingCache);

        if ($resolvedFlightId !== $flightId) {
            MyAgentFlightPickCache::put($flightId, $bookingCache);
        }

        Cache::put('myagent_offer_' . md5($resolvedFlightId), $flight, now()->addMinutes(30));

        if ($resolvedFlightId !== $flightId) {
            Cache::put('myagent_offer_' . md5($flightId), $flight, now()->addMinutes(30));
        }

        return [
            'success' => true,
            'checks' => $checks,
            'raw_meta' => [
                'pid' => $response['pid'] ?? null,
                'execution' => $response['time']['execution'] ?? null,
            ],
        ];
    }

    private function extractFlight(array $data): ?array
    {
        if (isset($data['flight']) && is_array($data['flight'])) {
            return $data['flight'];
        }

        if (isset($data['flights'][0]) && is_array($data['flights'][0])) {
            $item = $data['flights'][0];

            return is_array($item['flight'] ?? null) ? $item['flight'] : $item;
        }

        if (isset($data['id'])) {
            return $data;
        }

        return null;
    }

    private function extractBookingChecks(array $data, array $flight): array
    {
        $healthText = $this->resolveField($data, $flight, 'health_declaration_text');
        $healthAdditionalInfo = $this->resolveField($data, $flight, 'health_declaration_additional_info');
        $healthRequired = $this->isHealthDeclarationRequired($data, $flight, $healthText);

        return [
            'HealthDeclaration' => [
                'Required' => $healthRequired,
                'AdditionalInfo' => $healthAdditionalInfo,
                'Text' => $healthText,
            ],
            'Citizenships' => [
                'AllowedAny' => (bool) ($this->resolveField($data, $flight, 'is_allowed_any_citizenship') ?? true),
                'Allowed' => array_values($this->resolveField($data, $flight, 'allowed_citizenships') ?? []),
                'Prohibited' => array_values($this->resolveField($data, $flight, 'prohibited_citizenships') ?? []),
            ],
        ];
    }

    private function resolveField(array $data, array $flight, string $field): mixed
    {
        $value = $flight[$field] ?? $data[$field] ?? null;

        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function isHealthDeclarationRequired(array $data, array $flight, ?string $healthText): bool
    {
        if ((bool) ($this->resolveField($data, $flight, 'is_health_declaration_checked') ?? false)) {
            return true;
        }

        return $healthText !== null;
    }

    private function buildBookingCache(string $flightId, array $checks): array
    {
        return [
            'flight_id' => $flightId,
            'is_health_declaration_required' => $checks['HealthDeclaration']['Required'],
            'health_declaration' => [
                'additional_info' => $checks['HealthDeclaration']['AdditionalInfo'],
                'text' => $checks['HealthDeclaration']['Text'],
            ],
            'citizenships' => $checks['Citizenships'],
            'picked_at' => now()->timestamp,
        ];
    }
}
