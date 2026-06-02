<?php

namespace App\Services\ETG\Dumps;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReviewDumpImporter extends AbstractDumpImporter
{
    public const SUPPORTED_LANGUAGES = ['en'];
    public const BASE_LANGUAGE       = 'en';

    public function getApiEndpoint(): string
    {
        return '/api/b2b/v3/hotel/reviews/dump/';
    }

    public function getTable(): string
    {
        return 'hotel_reviews';
    }

    public function getSyncPrefix(): string
    {
        return 'review_dump';
    }

    public function getStorageDir(): string
    {
        return 'etg/reviews';
    }

    public function getPrimaryKey(): string
    {
        return 'id';
    }

    protected function getRequestBody(string $language): object|array
    {
        return ['language' => 'en'];
    }

    /**
     * Reviews use a simple decompress-then-insert flow instead of streaming,
     * so no Redis lock is needed.
     */
    public function requiresImportLock(): bool
    {
        return false;
    }

    private const STAGING_TABLE = 'hotel_reviews_import';

    /**
     * Import flow:
     *   1. If the path is a .zst archive, decompress it to a .json file on disk first.
     *   2. json_decode the full file (review dump is ~66 MB decompressed — fits in memory).
     *   3. Iterate the slug-keyed hotel map and batch-insert all reviews.
     *   4. Atomic RENAME TABLE swap (zero downtime).
     *   5. Delete the decompressed .json file.
     */
    public function importRecords(string $jsonlPath, string $language, ?callable $onBatch = null): int
    {
        if (!$this->isBaseLanguage($language)) {
            return 0;
        }

        // Decompress .zst → .json on disk; skip if already decompressed.
        $jsonPath     = $jsonlPath;
        $ownedJsonFile = false;
        if (str_ends_with($jsonlPath, '.zst')) {
            $jsonPath      = $this->decompressDump($jsonlPath);
            $ownedJsonFile = true;
        }

        try {
            return $this->importFromJsonFile($jsonPath, $onBatch);
        } finally {
            if ($ownedJsonFile && file_exists($jsonPath)) {
                @unlink($jsonPath);
                $this->log()->info('[review_dump] Deleted decompressed JSON file.', ['path' => $jsonPath]);
            }
        }
    }

    private function importFromJsonFile(string $jsonPath, ?callable $onBatch): int
    {
        $this->log()->info('[review_dump][en] Loading decompressed JSON file.', [
            'path'       => basename($jsonPath),
            'size_bytes' => @filesize($jsonPath),
        ]);

        $content = file_get_contents($jsonPath);
        if ($content === false) {
            throw new \RuntimeException("[review_dump] Cannot read file: {$jsonPath}");
        }

        $data = json_decode($content, true);
        unset($content);

        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('[review_dump] json_decode failed: ' . json_last_error_msg());
        }

        $hotelCount = count($data);
        $this->log()->info('[review_dump][en] JSON decoded.', ['hotel_count' => $hotelCount]);

        // Log sample for debugging field names.
        $firstSlug  = array_key_first($data);
        $firstHotel = $data[$firstSlug] ?? null;
        if (is_array($firstHotel)) {
            $this->log()->info('[review_dump] First hotel sample.', [
                'slug'             => $firstSlug,
                'hid'              => $firstHotel['hid'] ?? null,
                'reviews_count'    => isset($firstHotel['reviews']) ? count($firstHotel['reviews']) : 0,
                'hotel_keys'       => array_keys($firstHotel),
                'first_review_keys'=> isset($firstHotel['reviews'][0]) ? array_keys($firstHotel['reviews'][0]) : [],
            ]);
        }

        // Create staging table for zero-downtime swap.
        DB::statement('DROP TABLE IF EXISTS `' . self::STAGING_TABLE . '`');
        DB::statement('CREATE TABLE `' . self::STAGING_TABLE . '` LIKE `hotel_reviews`');
        DB::disableQueryLog();
        DB::statement('SET foreign_key_checks=0');
        DB::statement('SET unique_checks=0');

        $batch         = [];
        $totalInserted = 0;
        $batchCount    = 0;
        $totalReviews  = 0;
        $skippedNoHid  = 0;
        $skippedNoRev  = 0;
        $skippedBadMap = 0;
        $now           = now();

        // hotel_id → detailed_ratings (all 8 numeric dimension scores from ETG aggregate).
        $hotelScores = [];

        DB::beginTransaction();

        try {
            foreach ($data as $slug => $hotel) {
                if (!is_array($hotel)) {
                    continue;
                }

                $hotelId = (int) ($hotel['hid'] ?? $hotel['id'] ?? $hotel['hotel_id'] ?? 0);
                if ($hotelId <= 0) {
                    $skippedNoHid++;
                    continue;
                }

                // Collect hotel-level dimension scores from the ETG-computed aggregate.
                $dr = $hotel['detailed_ratings'] ?? null;
                if (is_array($dr)) {
                    $hotelScores[$hotelId] = $dr;
                }

                $reviews = $hotel['reviews'] ?? [];
                if (!is_array($reviews) || empty($reviews)) {
                    $skippedNoRev++;
                    continue;
                }

                foreach ($reviews as $r) {
                    if (!is_array($r)) {
                        continue;
                    }

                    $row = $this->mapReview($r, $hotelId, $now);
                    if ($row === null) {
                        $skippedBadMap++;
                        continue;
                    }

                    $batch[] = $row;
                    $totalReviews++;

                    if (count($batch) >= static::BATCH_SIZE) {
                        DB::table(self::STAGING_TABLE)->insert($batch);
                        $totalInserted += count($batch);
                        $batchCount++;
                        $batch = [];

                        if ($batchCount % static::COMMIT_EVERY === 0) {
                            DB::commit();
                            if ($onBatch !== null) {
                                ($onBatch)($totalReviews, $totalInserted);
                            }
                            DB::beginTransaction();
                        }
                    }
                }
            }

            if (!empty($batch)) {
                DB::table(self::STAGING_TABLE)->insert($batch);
                $totalInserted += count($batch);
                if ($onBatch !== null) {
                    ($onBatch)($totalReviews, $totalInserted);
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            DB::statement('DROP TABLE IF EXISTS `' . self::STAGING_TABLE . '`');
            throw $e;
        } finally {
            DB::statement('SET foreign_key_checks=1');
            DB::statement('SET unique_checks=1');
        }

        $this->log()->info('[review_dump][en] Insert complete.', [
            'hotels_seen'    => $hotelCount,
            'skipped_no_hid' => $skippedNoHid,
            'skipped_no_rev' => $skippedNoRev,
            'skipped_bad_map'=> $skippedBadMap,
            'total_reviews'  => $totalReviews,
            'total_inserted' => $totalInserted,
        ]);

        // Atomic swap: staging → live.
        $this->log()->info('[review_dump][en] Atomically swapping staging table into place.');
        DB::statement(
            'RENAME TABLE `hotel_reviews` TO `hotel_reviews_old`, `' . self::STAGING_TABLE . '` TO `hotel_reviews`'
        );
        DB::statement('DROP TABLE IF EXISTS `hotel_reviews_old`');

        // Upsert hotel-level dimension scores from the ETG aggregate into hotel_review_stats.
        $this->upsertHotelScores($hotelScores, $now);

        $this->log()->info('[review_dump][en] Import complete.', ['total_inserted' => $totalInserted]);

        return $totalInserted;
    }

    /**
     * Upsert hotel-level dimension scores from the dump's `detailed_ratings` into
     * hotel_review_stats. These 8 scores (including wifi/hygiene) come from ETG's
     * pre-computed aggregate and cannot be derived from per-review data alone.
     *
     * @param array<int, array<string, mixed>> $hotelScores hotel_id → detailed_ratings
     */
    private function upsertHotelScores(array $hotelScores, \Carbon\Carbon $now): void
    {
        if (empty($hotelScores)) {
            return;
        }

        $this->log()->info('[review_dump] Upserting hotel dimension scores.', ['hotels' => count($hotelScores)]);

        $scoreDimensions = ['cleanness', 'location', 'price', 'services', 'room', 'meal', 'wifi', 'hygiene'];
        $rows = [];

        foreach ($hotelScores as $hotelId => $dr) {
            $row = [
                'hotel_id'   => $hotelId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            foreach ($scoreDimensions as $dim) {
                $v = $dr[$dim] ?? null;
                $row['score_' . $dim] = is_numeric($v) ? (float) $v : null;
            }
            $rows[] = $row;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('hotel_review_stats')->upsert(
                $chunk,
                ['hotel_id'],
                array_map(fn (string $d) => 'score_' . $d, $scoreDimensions)
            );
        }

        $this->log()->info('[review_dump] Hotel dimension scores upserted.');
    }

    private function mapReview(array $r, int $hotelId, Carbon $now): ?array
    {
        $rating = $this->extractRating($r);
        if ($rating === null) {
            return null;
        }

        $comment   = $this->extractComment($r);
        $createdAt = $this->extractCreatedAt($r, $now);

        $d = null;
        if (isset($r['detailed']) && is_array($r['detailed'])) {
            $d = $r['detailed'];
        } elseif (isset($r['detailed_review']) && is_array($r['detailed_review'])) {
            $d = $r['detailed_review'];
        }

        return [
            'hotel_id'        => $hotelId,
            'rating'          => (float) $rating,
            'comment'         => $comment !== null && $comment !== '' ? self::str($comment, 65535) : null,
            'author_name'     => self::str($r['author'] ?? $r['author_name'] ?? $r['username'] ?? null, 255),
            'room_name'       => self::str($r['room_name'] ?? null, 500),
            'adults'          => isset($r['adults']) ? (int) $r['adults'] : null,
            'children'        => isset($r['children']) ? (int) $r['children'] : null,
            'traveller_type'  => self::str($r['traveller_type'] ?? null, 50),
            'trip_type'       => self::str($r['trip_type'] ?? null, 50),
            'score_cleanness' => $this->numericScore($d, 'cleanness'),
            'score_location'  => $this->numericScore($d, 'location'),
            'score_price'     => $this->numericScore($d, 'price'),
            'score_services'  => $this->numericScore($d, 'services'),
            'score_room'      => $this->numericScore($d, 'room'),
            'score_meal'      => $this->numericScore($d, 'meal'),
            'created_at'      => $createdAt,
            'updated_at'      => $now,
        ];
    }

    /**
     * Extract a numeric score for a single dimension from the review's `detailed` block.
     * Returns null for missing keys, non-numeric values (e.g. "perfect", "slow"), or zero.
     */
    private function numericScore(?array $detailed, string $key): ?float
    {
        if ($detailed === null) {
            return null;
        }
        $v = $detailed[$key] ?? null;
        if (!is_numeric($v) || (float) $v <= 0) {
            return null;
        }
        return (float) $v;
    }

    private function extractComment(array $r): ?string
    {
        foreach (['review_text', 'text', 'comment', 'body'] as $key) {
            $v = $r[$key] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        $review = $r['review'] ?? null;
        if (is_string($review) && trim($review) !== '') {
            return trim($review);
        }

        $plus  = $r['review_plus'] ?? $r['pros'] ?? null;
        $minus = $r['review_minus'] ?? $r['cons'] ?? null;
        $parts = array_filter([$plus, $minus], fn ($v) => $v !== null && $v !== '');

        return empty($parts) ? null : implode("\n\n", $parts);
    }

    private function extractRating(array $r): ?float
    {
        if (isset($r['rating']) && (float) $r['rating'] > 0) {
            return (float) $r['rating'];
        }

        $d = null;
        if (isset($r['detailed']) && is_array($r['detailed'])) {
            $d = $r['detailed'];
        } elseif (isset($r['detailed_review']) && is_array($r['detailed_review'])) {
            $d = $r['detailed_review'];
        }

        if ($d === null) {
            return null;
        }

        $vals = array_values(array_filter([
            $d['cleanness'] ?? null,
            $d['location'] ?? null,
            $d['price'] ?? null,
            $d['services'] ?? null,
            $d['room'] ?? null,
            $d['meal'] ?? null,
            $d['wifi'] ?? null,
            $d['hygiene'] ?? null,
        ], fn ($v) => is_numeric($v) && (float) $v > 0));

        if (!empty($vals)) {
            return (float) (array_sum($vals) / count($vals));
        }

        if (isset($d['rating']) && (float) $d['rating'] > 0) {
            return (float) $d['rating'];
        }

        return null;
    }

    private function extractCreatedAt(array $r, Carbon $fallback): ?Carbon
    {
        $date = $r['created'] ?? $r['created_at'] ?? $r['date'] ?? null;
        if ($date === null) {
            return $fallback;
        }
        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    protected function mapBaseRecord(array $data, Carbon $now): ?array
    {
        return null;
    }

    protected function mapTranslationFields(array $data, string $language): ?array
    {
        return null;
    }

    public function getLastUpdateFromExistingFile(string $language): ?string
    {
        $dir   = Storage::disk('local')->path($this->getStorageDir());
        $glob  = $dir . '/' . $this->getSyncPrefix() . '_' . $language . '_*.jsonl.zst';
        $files = glob($glob);

        if (empty($files)) {
            return null;
        }

        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));
        $basename = basename($files[0]);
        $prefix   = $this->getSyncPrefix() . '_' . $language . '_';
        if (str_starts_with($basename, $prefix)) {
            $datePart = substr($basename, strlen($prefix), 10);
            if (preg_match('/^\d{4}_\d{2}_\d{2}$/', $datePart)) {
                return str_replace('_', '-', $datePart) . 'T00:00:00Z';
            }
        }

        return null;
    }

    public function aggregateHotelReviewStats(): void
    {
        $this->log()->info('[review_dump] Aggregating hotel review statistics.');

        // Update reviews_count and avg_rating from the live hotel_reviews table.
        // Dimension scores (score_cleanness … score_hygiene) are left untouched —
        // they were already written directly from the ETG aggregate during import.
        DB::statement('
            INSERT INTO hotel_review_stats (hotel_id, reviews_count, avg_rating, created_at, updated_at)
            SELECT hotel_id, COUNT(*), AVG(rating), NOW(), NOW()
            FROM hotel_reviews
            GROUP BY hotel_id
            ON DUPLICATE KEY UPDATE
                reviews_count = VALUES(reviews_count),
                avg_rating    = VALUES(avg_rating),
                updated_at    = VALUES(updated_at)
        ');

        $this->log()->info('[review_dump] Hotel review statistics aggregated.');
    }
}
