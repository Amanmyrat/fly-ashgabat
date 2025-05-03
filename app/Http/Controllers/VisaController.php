<?php

namespace App\Http\Controllers;

use App\Http\Resources\VisaResource;
use App\Models\Visa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VisaController extends Controller
{
    /**
     * All visas
     *
     * @localizationHeader
     */
    public function getAllVisas(): AnonymousResourceCollection
    {
        $destinations = Visa::orderBy('order', 'asc')->get();

        return VisaResource::collection($destinations);
    }

    /**
     * Get visas
     *
     * @localizationHeader
     */
    public function getVisas(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $limit = $request->input('limit', 10);

        $query = Visa::query();
        $visas = $query->paginate($limit);

        return VisaResource::collection($visas);
    }

    /**
     * Get visa details
     *
     * @localizationHeader
     */
    public function getVisaDetails(Visa $visa): VisaResource
    {
        return new VisaResource($visa);
    }
}
