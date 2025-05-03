<?php

namespace App\Http\Controllers;

use App\Http\Resources\TourResource;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TourController extends Controller
{
    /**
     * All tours
     *
     * @localizationHeader
     */
    public function getAllTours(): AnonymousResourceCollection
    {
        $destinations = Tour::orderBy('order', 'asc')->get();

        return TourResource::collection($destinations);
    }

    /**
     * Get tours
     *
     * @localizationHeader
     */
    public function getTours(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $limit = $request->input('limit', 10);

        $query = Tour::query();
        $tours = $query->paginate($limit);

        return TourResource::collection($tours);
    }

    /**
     * Get tour details
     *
     * @localizationHeader
     */
    public function getTourDetails(Tour $tour): TourResource
    {
        return new TourResource($tour);
    }
}
