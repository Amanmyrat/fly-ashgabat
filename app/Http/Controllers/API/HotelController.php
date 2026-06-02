<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\HotelPageRequest;
use App\Models\Hotel;
use App\Services\ETG\HotelPageService;
use App\Support\EtgLanguage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/** Hotel page (details and rates). */
class HotelController extends Controller
{
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly HotelPageService $pageService,
    ) {}

    /** Hotel details.
     *
     * @localizationHeader
     *
     */
    public function show(HotelPageRequest $request): JsonResponse
    {
        $hid = $this->resolveHotelHid($request->input('hotel'));
        if ($hid === null) {
            return response()->json([
                'success' => false,
                'message' => 'Hotel not found.',
            ], 404);
        }

        $params = [
            'checkin'  => $request->input('checkin'),
            'checkout' => $request->input('checkout'),
            'language' => EtgLanguage::resolve(),
            'guests'   => $request->input('guests'),
        ];

        $cacheKey = 'etg_hotel_page:' . $hid . ':' . $params['checkin'] . ':' . $params['checkout'] . ':' . md5(json_encode($params['guests'])) . ':' . $params['language'];

        $hotelDto = Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->pageService->getHotelPage($hid, $params));

        if ($hotelDto === null) {
            return response()->json([
                'success' => false,
                'message' => 'Hotel not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $hotelDto->toApiResponse(),
        ]);
    }

    /** hid if numeric, else lookup by `etg_id` */
    private function resolveHotelHid(string $hotel): ?int
    {
        $hotel = trim($hotel);
        if ($hotel === '') {
            return null;
        }

        if (ctype_digit($hotel)) {
            return (int) $hotel;
        }

        return Hotel::where('etg_id', $hotel)->value('hid');
    }
}
