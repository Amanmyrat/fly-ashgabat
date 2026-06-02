<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Hotel and region autocomplete. */
class SearchController extends Controller
{
    private const MAX_HOTELS   = 8;

    /** Kinds shown in autocomplete, in priority order. Everything else is excluded. */
    private const HOTEL_KINDS = ['hotel', 'hostel', 'apartment', 'motel'];
    private const MAX_REGIONS  = 5;
    private const MIN_QUERY    = 2;
    /**
     * innodb_ft_min_token_size default is 3 — FULLTEXT ignores tokens shorter than this.
     * For 2-char queries we fall back to a B-tree prefix LIKE scan instead.
     */
    private const FT_MIN_LEN   = 3;
    private const CACHE_TTL    = 600; // 10 minutes
    private const CACHE_VER    = 'v3';

    /** Autocomplete. @operationId autocomplete */
    public function autocomplete(Request $request): JsonResponse
    {
        $query = trim($request->string('q'));

        // Auto-detect Cyrillic so the caller doesn't need to pass lang=ru.
        $hasCyrillic = (bool) preg_match('/[\x{0400}-\x{04FF}]/u', $query);
        $lang = $hasCyrillic
            ? 'ru'
            : (in_array($request->string('lang', 'en'), ['en', 'ru'], true)
                ? $request->string('lang', 'en')
                : 'en');

        if (mb_strlen($query) < self::MIN_QUERY) {
            return response()->json(['hotels' => [], 'regions' => []]);
        }

        $cacheKey = 'hotel_search:' . self::CACHE_VER . ':' . $lang . ':' . mb_strtolower($query);

        $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($query, $lang) {
            return [
                'hotels'  => $this->searchHotels($query, $lang),
                'regions' => $this->searchRegions($query, $lang),
            ];
        });

        return response()->json($result);
    }

    private function searchHotels(string $query, string $lang): array
    {
        $kinds        = self::HOTEL_KINDS;
        $kindListSql  = implode(',', array_map(fn ($k) => DB::connection()->getPdo()->quote($k), $kinds));
        $kindPlace    = implode(',', array_fill(0, count($kinds), '?'));

        $hotelsQuery = Hotel::query()
            ->join('regions', 'regions.id', '=', 'hotels.region_id')
            ->select([
                'hotels.hid',
                'hotels.region_id',
                'hotels.country_code',
                'hotels.kind',
                'hotels.star_rating',
                'hotels.etg_id',
            ])
            ->addSelect([
                DB::raw("hotels.name_{$lang} as name"),
                DB::raw("hotels.address_{$lang} as address"),
                DB::raw("regions.name_{$lang} as region_name"),
                DB::raw("regions.country_name_{$lang} as country_name"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(hotels.images, '\$[0]')) as image"),
            ])
            ->whereRaw("LOWER(hotels.kind) IN ({$kindPlace})", $kinds);

        if (mb_strlen($query) >= self::FT_MIN_LEN) {
            $ftQuery    = $this->buildFtQuery($query);
            $prefixLike = $query . '%';
            $exactLower = mb_strtolower($query);

            $hotelsQuery
                ->whereRaw(
                    "MATCH(hotels.name_{$lang}, hotels.address_{$lang}) AGAINST (? IN BOOLEAN MODE)",
                    [$ftQuery]
                )
                ->orderByRaw("(LOWER(hotels.name_{$lang}) = ?) DESC", [$exactLower])
                ->orderByRaw("(hotels.name_{$lang} LIKE ?) DESC", [$prefixLike])
                ->orderByRaw("FIELD(LOWER(hotels.kind), {$kindListSql}) ASC")
                ->orderByDesc('hotels.star_rating');
        } else {
            $prefixLike = $query . '%';
            $exactLower = mb_strtolower($query);

            $hotelsQuery
                ->where("hotels.name_{$lang}", 'like', $prefixLike)
                ->orderByRaw("(LOWER(hotels.name_{$lang}) = ?) DESC", [$exactLower])
                ->orderByRaw("FIELD(LOWER(hotels.kind), {$kindListSql}) ASC")
                ->orderByDesc('hotels.star_rating');
        }

        return $hotelsQuery
            ->limit(self::MAX_HOTELS)
            ->get()
            ->map(fn (Hotel $h) => [
                'hid'          => (int) $h->hid,
                'etg_id'       => $h->etg_id,
                'name'         => $h->name,
                'region_id'    => (int) $h->region_id,
                'region_name'  => $h->region_name,
                'country_code' => $h->country_code,
                'country_name' => $h->country_name,
                'kind'         => $h->kind,
                'address'      => $h->address,
                'star_rating'  => $h->star_rating !== null ? (int) $h->star_rating : null,
                'image'        => $h->image,
            ])
            ->all();
    }

    private function searchRegions(string $query, string $lang): array
    {
        $nameCol    = "r.name_{$lang}";
        $prefixLike = $query . '%';
        $exactLower = mb_strtolower($query);

        if (mb_strlen($query) >= self::FT_MIN_LEN) {
            $ftQuery = $this->buildFtQuery($query);

            $rows = DB::select("
                SELECT
                    r.id,
                    r.type,
                    r.iata,
                    {$nameCol}              AS name,
                    r.country_code,
                    r.country_name_{$lang}  AS country_name
                FROM regions r
                WHERE MATCH(r.name_{$lang}) AGAINST(? IN BOOLEAN MODE)
                ORDER BY
                    (LOWER({$nameCol}) = ?)                      DESC,
                    ({$nameCol} LIKE ?)                           DESC,
                    FIELD(r.type, 'District', 'Country', 'Airport', 'Resort', 'City') DESC,
                    (r.iata IS NOT NULL)                          DESC
                LIMIT " . self::MAX_REGIONS,
                [$ftQuery, $exactLower, $prefixLike]
            );
        } else {
            $rows = DB::select("
                SELECT
                    r.id,
                    r.type,
                    r.iata,
                    {$nameCol}              AS name,
                    r.country_code,
                    r.country_name_{$lang}  AS country_name
                FROM regions r
                WHERE {$nameCol} LIKE ?
                ORDER BY
                    (LOWER({$nameCol}) = ?)                      DESC,
                    FIELD(r.type, 'District', 'Country', 'Airport', 'Resort', 'City') DESC,
                    (r.iata IS NOT NULL)                          DESC
                LIMIT " . self::MAX_REGIONS,
                [$prefixLike, $exactLower]
            );
        }

        return array_map(fn ($row) => [
            'id'           => (int) $row->id,
            'type'         => $row->type,
            'iata'         => $row->iata ?? null,
            'name'         => $row->name,
            'country_code' => $row->country_code,
            'country_name' => $row->country_name,
        ], $rows);
    }

    /**
     * Convert a plain search string into a FULLTEXT boolean mode query with prefix matching.
     *
     * "hilton garden" → "+hilton* +garden*"
     * "ashg"          → "+ashg*"
     *
     * Special boolean operators are stripped from each token to prevent syntax errors.
     */
    private function buildFtQuery(string $query): string
    {
        $tokens = array_values(array_filter(preg_split('/\s+/', trim($query))));

        return implode(' ', array_map(
            fn (string $t) => '+' . preg_replace('/[+\-><()"~*@]+/', '', $t) . '*',
            $tokens
        ));
    }
}
