<?php

namespace App\Services\MyAgent;

use App\Services\MyAgent\RequestBuilder\SearchRecommendationsRequestBuilder;
use App\Services\MyAgent\Transformers\FlightRecommendationTransformer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FlightSearchService
{
    protected int $minSeats;

    public function __construct(
        protected MyAgentService $myAgentService,
        protected FlightRecommendationTransformer $transformer
    ) {
        $this->minSeats = (int) config('myagent.min_seats', 5);
    }

    /**
     * @throws \Exception
     */
    public function search(array $validatedData): array
    {
        $requestBuilder = new SearchRecommendationsRequestBuilder($validatedData);
        $query = $requestBuilder->build();

        $rawResponse = $this->myAgentService->get('/avia/search-recommendations', $query);

        $recommendations = $this->filterByMinimumSeats(
            $rawResponse['data']['flights'] ?? []
        );
        $searchData = $rawResponse['data']['search'] ?? [];

        $searchGuid = $searchData['token']
            ?? 'myagent_' . Str::uuid()->toString();

        Cache::put('myagent_search_' . $searchGuid, $rawResponse, now()->addMinutes(30));

        foreach ($recommendations as $recommendation) {
            if (!empty($recommendation['id'])) {
                Cache::put('myagent_offer_' . md5($recommendation['id']), $recommendation, now()->addMinutes(30));
            }
        }

        return [
            'success' => (bool) ($rawResponse['success'] ?? true),
            'flights' => $this->transformer->transformMany($recommendations, $searchData),
            'raw_meta' => [
                'pid' => $rawResponse['pid'] ?? null,
                'execution' => $rawResponse['time']['execution'] ?? null,
            ],
        ];
    }

    private function filterByMinimumSeats(array $recommendations): array
    {
        if ($this->minSeats <= 0) {
            return $recommendations;
        }

        return array_values(array_filter(
            $recommendations,
            fn (array $recommendation) => $this->meetsMinimumSeats($recommendation)
        ));
    }

    private function meetsMinimumSeats(array $recommendation): bool
    {
        $segments = $recommendation['segments'] ?? [];

        if ($segments === []) {
            return false;
        }

        foreach ($segments as $segment) {
            $seats = $segment['seats'] ?? null;

            if ($seats === null || (int) $seats < $this->minSeats) {
                return false;
            }
        }

        return true;
    }
}
