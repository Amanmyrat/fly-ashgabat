<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelReview;
use App\Models\HotelReviewStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Hotel reviews. */
class HotelReviewsController extends Controller
{
    /** List reviews.
     *
     * @localizationHeader
     *
     */
    public function index(Request $request, int $hid): JsonResponse
    {
        $hotel = Hotel::query()->find($hid);

        if (!$hotel) {
            return response()->json([
                'success' => false,
                'message' => 'Hotel not found.',
            ], 404);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        $reviewsQuery = HotelReview::query()->where('hotel_id', $hid);

        $reviews = (clone $reviewsQuery)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $reviewsCount = $reviews->total();
        $avgRating    = (clone $reviewsQuery)->avg('rating');
        $avgRating    = $avgRating !== null
            ? round((float) $avgRating, 2)
            : null;

        /** @var HotelReviewStats|null $stats */
        $stats       = HotelReviewStats::query()->find($hid);
        $hotelScores = $stats !== null ? [
            'cleanness' => $stats->score_cleanness,
            'location'  => $stats->score_location,
            'price'     => $stats->score_price,
            'services'  => $stats->score_services,
            'room'      => $stats->score_room,
            'meal'      => $stats->score_meal,
            'wifi'      => $stats->score_wifi,
            'hygiene'   => $stats->score_hygiene,
        ] : null;

        $items = array_map(fn (HotelReview $r) => [
            'id'             => $r->id,
            'rating'         => $r->rating,
            'comment'        => $r->comment,
            'author_name'    => $r->author_name,
            'room_name'      => $r->room_name,
            'adults'         => $r->adults,
            'children'       => $r->children,
            'traveller_type' => $r->traveller_type,
            'trip_type'      => $r->trip_type,
            'scores'         => [
                'cleanness' => $r->score_cleanness,
                'location'  => $r->score_location,
                'price'     => $r->score_price,
                'services'  => $r->score_services,
                'room'      => $r->score_room,
                'meal'      => $r->score_meal,
            ],
            'created_at'     => $r->created_at?->toIso8601String() ?? null,
        ], $reviews->items());

        return response()->json([
            'success' => true,
            'data'    => [
                'hotel_id'      => $hid,
                'reviews_count' => $reviewsCount,
                'avg_rating'    => $avgRating,
                'hotel_scores'  => $hotelScores,
                'reviews'       => $items,
                'pagination'    => [
                    'current_page' => $reviews->currentPage(),
                    'last_page'    => $reviews->lastPage(),
                    'per_page'     => $reviews->perPage(),
                    'total'        => $reviews->total(),
                ],
            ],
        ]);
    }
}
