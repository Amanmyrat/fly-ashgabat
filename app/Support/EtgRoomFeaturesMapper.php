<?php

namespace App\Support;

use App\Enum\BathroomType;
use App\Enum\RoomClassType;

class EtgRoomFeaturesMapper
{
    public static function map(?array $rgExt): array
    {
        if (!$rgExt) {
            return [];
        }

        return [
            'bathroom' => BathroomType::tryFrom(
                (int) ($rgExt['bathroom'] ?? 0)
            )?->key(),

            'room_class' => RoomClassType::tryFrom(
                (int) ($rgExt['class'] ?? 0)
            )?->key(),

            'balcony' => ((int) ($rgExt['balcony'] ?? 0)) === 1,

            'club_access' => ((int) ($rgExt['club'] ?? 0)) === 1,

            'family_room' => ((int) ($rgExt['family'] ?? 0)) === 1,

            'bedrooms' => (int) ($rgExt['bedrooms'] ?? 0),

            'capacity' => (int) ($rgExt['capacity'] ?? 0),
        ];
    }
}
