<?php

namespace App\Http\Controllers\API;

use App\DTO\HotelSearchRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\HotelSearchRequest;
use App\Services\HotelSearchFacetAndFilterService;
use App\Services\ETG\HotelSearchService;
use App\Support\EtgLanguage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/** Hotel search. */
class HotelSearchController extends Controller
{
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly HotelSearchService $searchService,
        private readonly HotelSearchFacetAndFilterService $facetAndFilter,
    ) {}

    /** Search by region.
     *
     * @localizationHeader
     *
     */
    public function searchByRegion(HotelSearchRequest $request): JsonResponse
    {
        $dto = new HotelSearchRequestDTO(
            regionId: $request->input('region_id'),
            checkin: $request->input('checkin'),
            checkout: $request->input('checkout'),
            language: EtgLanguage::resolve(),
            guests: $request->input('guests'),
        );

        $cacheKey = 'etg_hotel_search:v3:' . $dto->regionId . ':' . $dto->checkin . ':' . $dto->checkout . ':' . md5(json_encode($dto->guests)) . ':' . $dto->language;

        $result = Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->searchService->search($dto));

        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);
        $page    = max((int) $request->input('page', 1), 1);

        if ($result['total_hotels'] === 0 && empty($result['hotels'])) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'total_hotels' => 0,
                    'hotels'       => [],
                    'region'       => $result['region'] ?? null,
                    'filter_values' => $this->facetAndFilter->buildFacets([]),
                    'pagination'   => [
                        'current_page' => 1,
                        'last_page'    => 1,
                        'per_page'     => $perPage,
                        'total'        => 0,
                    ],
                ],
                'message' => 'No hotels found for the given criteria.',
            ]);
        }

        $allHotels = $result['hotels'];
        $filterValues = $this->facetAndFilter->buildFacets($allHotels);
        /** @var array<string, mixed> $filtersIn */
        $filtersIn = $request->validated('filters', []);
        if (!is_array($filtersIn)) {
            $filtersIn = [];
        }
        $hotels = $this->facetAndFilter->applyFilters($allHotels, $filtersIn);

        $sortBy = $request->input('sort_by');
        $hotels = $this->facetAndFilter->applySorting($hotels, $sortBy);

        $total    = count($hotels);
        $lastPage = (int) max(1, (int) ceil($total / $perPage));
        $page     = min($page, $lastPage);
        $offset   = ($page - 1) * $perPage;
        $pageHotels = array_slice($hotels, $offset, $perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'region'       => $result['region'],
                'total_hotels' => $result['total_hotels'],
                'hotels'       => $pageHotels,
                'filter_values' => $filterValues,
                'sort_values' => $this->sortValues(),
                'pagination'   => [
                    'current_page' => $page,
                    'last_page'    => $lastPage,
                    'per_page'     => $perPage,
                    'total'        => $total,
                ],
            ],
        ]);
    }

    private function sortValues(): array
    {
        return [
            ['value' => 'price_asc', 'label' => 'Price: Low to High'],
            ['value' => 'price_desc', 'label' => 'Price: High to Low'],
            ['value' => 'rating_desc', 'label' => 'Guest Rating: High to Low'],
            ['value' => 'rating_asc', 'label' => 'Guest Rating: Low to High'],
            ['value' => 'distance_asc', 'label' => 'Distance: Nearest First'],
            ['value' => 'distance_desc', 'label' => 'Distance: Farthest First'],
            ['value' => 'stars_desc', 'label' => 'Stars: High to Low'],
            ['value' => 'stars_asc', 'label' => 'Stars: Low to High'],
        ];
    }
}
