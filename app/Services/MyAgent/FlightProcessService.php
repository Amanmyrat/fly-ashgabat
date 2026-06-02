<?php

namespace App\Services\MyAgent;

use App\Services\MyAgent\RequestBuilder\FlightDetailsRequestBuilder;
use App\Services\MyAgent\Transformers\FlightRecommendationTransformer;
use Exception;
use Illuminate\Support\Facades\Cache;

class FlightProcessService
{
    public function __construct(
        protected MyAgentService $myAgentService,
        protected FlightRecommendationTransformer $transformer
    ) {
    }

    /**
     * @throws Exception
     */
    public function processFlight(array $validatedData): array
    {
        $operation = $validatedData['operation'] ?? 'All';

        $result = [
            'success' => true,
            'selected_flight' => $this->getCachedSelectedFlight($validatedData['id']),
            'tariffs' => [],
            'rules' => [],
        ];

        $builder = new FlightDetailsRequestBuilder($validatedData);

        if (in_array($operation, ['All', 'GetFareFamilies'], true)) {
            $fareFamiliesResponse = $this->myAgentService->get(
                '/avia/flightff',
                $builder->buildFareFamiliesQuery()
            );

            $result['tariffs'] = $this->transformFareFamilies($fareFamiliesResponse);
            $result['raw_fare_families_meta'] = [
                'pid' => $fareFamiliesResponse['pid'] ?? null,
                'execution' => $fareFamiliesResponse['time']['execution'] ?? null,
            ];
        }

        if (in_array($operation, ['All', 'GetFareRules'], true)) {
            $rulesResponse = $this->myAgentService->get(
                '/avia/rules',
                $builder->buildRulesQuery()
            );

            $result['rules'] = $this->transformRules($rulesResponse);
            $result['raw_rules_meta'] = [
                'pid' => $rulesResponse['pid'] ?? null,
                'execution' => $rulesResponse['time']['execution'] ?? null,
            ];
        }

        return $result;
    }

    private function getCachedSelectedFlight(string $id): ?array
    {
        $raw = Cache::get('myagent_offer_' . md5($id));

        if (!is_array($raw)) {
            return null;
        }

        return $this->transformer->transform($raw);
    }

    private function transformFareFamilies(array $response): array
    {
        $items = $response['data']['flights'] ?? [];

        $tariffs = [];

        foreach ($items as $item) {
            $flight = $item['flight'] ?? null;

            if (!is_array($flight)) {
                continue;
            }

            $transformed = $this->transformer->transform($flight);

            $tariffs[] = [
                'id' => $flight['id'] ?? null,
                'name' => $flight['fare_family_marketing_name']
                    ?? $flight['fare_family_type']
                        ?? null,
                'type' => $flight['fare_family_type'] ?? null,
                'price' => $transformed['TotalSum'] ?? null,
                'features' => $this->extractFeaturesFromMiniRules($flight['mini_rules'] ?? []),
                'flight' => $transformed,
            ];

            if (!empty($flight['id'])) {
                Cache::put('myagent_offer_' . md5($flight['id']), $flight, now()->addMinutes(30));
            }
        }

        return $tariffs;
    }

    private function extractFeaturesFromMiniRules(array $miniRules): array
    {
        $features = [];

        $map = [
            'baggage' => 'Checked baggage',
            'carry_on_baggage' => 'Cabin baggage',
            'accessories' => 'Personal item',
            'refund' => 'Refund',
            'exchange' => 'Exchange',
        ];

        foreach ($map as $key => $label) {
            $rule = $miniRules[$key] ?? null;

            if (!$rule) {
                continue;
            }

            if (in_array($key, ['refund', 'exchange'], true)) {
                $beforeDeparture = $rule['before_departure'] ?? [];

                $features[] = [
                    'text' => $beforeDeparture['comment'] ?? $label,
                    'enabled' => (bool) ($beforeDeparture['is_available'] ?? false),
                    'withCharge' => !((bool) ($beforeDeparture['is_free'] ?? false)),
                    'code' => $key,
                ];

                continue;
            }

            $features[] = [
                'text' => $rule['comment'] ?? $label,
                'enabled' => (bool) ($rule['is_available'] ?? false),
                'withCharge' => false,
                'code' => $key,
            ];
        }

        return $features;
    }

    private function transformRules(array $response): array
    {
        $rulesData = $response['data']['rules'] ?? [];

        $rules = [];

        foreach ($rulesData as $recommendationId => $items) {
            $items = is_array($items) ? $items : [$items];

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $rules[] = [
                    'recommendation_id' => $recommendationId,
                    'departure' => [
                        'Code' => $item['departure']['iata'] ?? null,
                        'Name' => $item['departure']['name'] ?? null,
                        'Country' => $item['departure']['country']['name'] ?? null,
                    ],
                    'arrival' => [
                        'Code' => $item['arrival']['iata'] ?? null,
                        'Name' => $item['arrival']['name'] ?? null,
                        'Country' => $item['arrival']['country']['name'] ?? null,
                    ],
                    'title' => trim(($item['departure']['iata'] ?? '') . ' → ' . ($item['arrival']['iata'] ?? '')),
                    'description' => $this->formatRuleText($item['text'] ?? ''),
                ];
            }
        }

        return $rules;
    }

    private function formatRuleText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
