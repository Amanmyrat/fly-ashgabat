<?php

namespace App\Services\MyAgent;

use App\Services\MyAgent\RequestBuilder\SearchRecommendationsRequestBuilder;
use App\Services\MyAgent\Transformers\FlightRecommendationTransformer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FlightSearchService
{
    public function __construct(
        protected MyAgentService $myAgentService,
        protected FlightRecommendationTransformer $transformer
    ) {
    }

    /**
     * @throws \Exception
     */
    public function search(array $validatedData): array
    {
        $requestBuilder = new SearchRecommendationsRequestBuilder($validatedData);
        $query = $requestBuilder->build();

        $rawResponse = $this->myAgentService->get('/avia/search-recommendations', $query);

        $recommendations = $rawResponse['data']['flights'] ?? [];
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
}
