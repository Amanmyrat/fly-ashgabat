<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        
        return [
            'id' => $this->resource->id,
            'name' => $this->getTranslatedValue($this->resource->getTranslations('name'), $locale),
            'description' => $this->getTranslatedValue($this->resource->getTranslations('description'), $locale),
            'location' => $this->getTranslatedValue($this->resource->getTranslations('location'), $locale),
            'days' => $this->resource->days,
            'included' => $this->getCleanRepeaterDataForLocale($this->resource->getTranslations('included'), $locale),
            'not_included' => $this->getCleanRepeaterDataForLocale($this->resource->getTranslations('not_included'), $locale),
            'main_image' => $this->resource->main_image ? asset('storage/' . $this->resource->main_image) : null,
            'background_image' => $this->resource->background_image ? asset('storage/' . $this->resource->background_image) : null,
        ];
    }

    /**
     * Get translated value for current locale with fallback
     */
    private function getTranslatedValue(array $translations, string $locale): ?string
    {
        // Try current locale first
        if (isset($translations[$locale])) {
            return $translations[$locale];
        }
        
        // Fallback to 'en' if available
        if (isset($translations['en'])) {
            return $translations['en'];
        }
        
        // Return first available translation
        return !empty($translations) ? reset($translations) : null;
    }

    /**
     * Clean and normalize repeater data for current locale only
     */
    private function getCleanRepeaterDataForLocale(array $translations, string $locale): array
    {
        // Try current locale first
        $data = $translations[$locale] ?? null;
        
        // Fallback to 'en' if current locale not available
        if (!$data && isset($translations['en'])) {
            $data = $translations['en'];
        }
        
        // Final fallback to first available
        if (!$data && !empty($translations)) {
            $data = reset($translations);
        }
        
        $cleaned = [];
        
        if (is_string($data)) {
            $data = json_decode($data, true) ?? [];
        }
        
        if (is_array($data)) {
            foreach ($data as $key => $item) {
                if (is_array($item) && isset($item['item'])) {
                    $cleaned[] = $item['item'];
                } elseif (is_string($item)) {
                    $cleaned[] = $item;
                }
            }
        }
        
        return $cleaned;
    }
}
