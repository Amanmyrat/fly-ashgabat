<?php

namespace App\Services\ETG;

class HotelReviewsService
{
    private const REVIEWS_ENDPOINT = 'api/content/v1/hotel_reviews_by_ids/';

    private const BATCH_SIZE = 50;

    public function __construct(private readonly EtgClient $client) {}

    /**
     * @param  array<int, int>  $hids
     * @return array<int, array{score: float|null, reviews_count: int, score_qualitative: string|null}>
     */
    public function getScoresByHids(array $hids, string $language = 'en'): array
    {
        $hids = array_values(array_unique(array_filter($hids, fn ($id) => $id > 0)));
        $result = array_fill_keys($hids, ['score' => null, 'reviews_count' => 0, 'score_qualitative' => null]);

        foreach (array_chunk($hids, self::BATCH_SIZE) as $chunk) {
            try {
                $response = $this->client->post(self::REVIEWS_ENDPOINT, [
                    'hids'     => $chunk,
                    'language' => $language,
                ]);
            } catch (\Throwable) {
                continue;
            }

            $items = $response['data'] ?? [];
            foreach ($items as $item) {
                $hid = (int) ($item['hid'] ?? 0);
                if ($hid <= 0) {
                    continue;
                }
                $reviews = $item['reviews'] ?? $item['review'] ?? [];
                $reviews = is_array($reviews) ? $reviews : [];
                $count = count($reviews);
                $score = $this->computeAverageScore($reviews);
                $qualitative = $score !== null ? $this->scoreToQualitative($score) : null;
                $result[$hid] = ['score' => $score, 'reviews_count' => $count, 'score_qualitative' => $qualitative];
            }
        }

        return $result;
    }

    private function scoreToQualitative(float $score): string
    {
        return match (true) {
            $score >= 9.0 => 'Excellent',
            $score >= 8.0 => 'Very good',
            $score >= 7.0 => 'Good',
            $score >= 6.0 => 'Pleasant',
            $score >= 5.0 => 'Acceptable',
            default      => 'Below average',
        };
    }

    /**
     * @param  array<int, mixed>  $reviews
     */
    private function computeAverageScore(array $reviews): ?float
    {
        if (empty($reviews)) {
            return null;
        }

        $sum = 0;
        $n = 0;
        foreach ($reviews as $r) {
            if (!is_array($r)) {
                continue;
            }
            $dr = $r['detailed_review'] ?? [];
            if (!is_array($dr)) {
                $rating = isset($r['rating']) ? (int) $r['rating'] : null;
                if ($rating !== null && $rating > 0) {
                    $sum += $rating * 2;
                    $n++;
                }
                continue;
            }
            $vals = array_filter([
                $dr['cleanness'] ?? null,
                $dr['location'] ?? null,
                $dr['price'] ?? null,
                $dr['services'] ?? null,
                $dr['room'] ?? null,
                $dr['meal'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
            if (!empty($vals)) {
                $sum += array_sum($vals) / count($vals);
                $n++;
            } elseif (isset($dr['rating']) && (int) $dr['rating'] > 0) {
                $sum += (int) $dr['rating'] * 2;
                $n++;
            }
        }

        return $n > 0 ? round($sum / $n, 1) : null;
    }
}
