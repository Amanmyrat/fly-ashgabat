<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Comprehensive hotel booking resource for user's booking details view. */
class UserHotelBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hotel = $this->hotel;
        $guests = $this->resource->guests ?? [];
        $apiResponse = $this->resource->api_response ?? [];

        $hotelData = null;
        if ($hotel) {
            $hotelData = [
                'id'              => $hotel->hid,
                'name'            => $hotel->name_en,
                'address'         => $hotel->address_en,
                'star_rating'     => $hotel->star_rating,
                'country_code'    => $hotel->country_code,
                'latitude'        => $hotel->latitude,
                'longitude'       => $hotel->longitude,
                'images'          => $hotel->images ?? [],
                'check_in_time'   => $hotel->check_in_time,
                'check_out_time'  => $hotel->check_out_time,
            ];
        }

        return [
            'id'               => $this->id,
            'partner_order_id' => $this->partner_order_id,
            'order_id'         => $this->etg_order_id,
            'status'           => $this->status,
            'payment_type'     => $this->payment_type,
            'amount'           => (float) $this->amount,
            'currency'         => $this->currency,
            'contact_email'    => $this->contact_email,
            'contact_phone'    => $this->contact_phone,
            'hotel'            => $hotelData,
            'room_details'     => [
                'room_type'      => $this->room_type,
                'rooms_count'    => $this->rooms_count,
                'adults_count'   => $this->adults_count,
                'children_count' => $this->children_count,
            ],
            'guests'           => $guests,
            'checkin'          => $this->parseCheckInDate($apiResponse, $guests),
            'checkout'         => $this->parseCheckOutDate($apiResponse, $guests),
            'documents'        => $this->confirmation_pdf_url
                ? [['name' => 'hotel-confirmation.pdf', 'url' => $this->confirmation_pdf_url]]
                : [],
            'amenities'        => $this->extractAmenities($apiResponse),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Extract check-in date from api_response or guests data.
     *
     * @param array<string, mixed> $apiResponse
     * @param array<string, mixed> $guests
     */
    private function parseCheckInDate(array $apiResponse, array $guests): ?string
    {
        if (!empty($apiResponse) && isset($apiResponse['book_data']['check_in'])) {
            return $apiResponse['book_data']['check_in'];
        }

        if (!empty($guests)) {
            $firstRoom = reset($guests);
            
            if (is_array($firstRoom)) {
                if (isset($firstRoom['checkin'])) {
                    return $firstRoom['checkin'];
                }
                
                if (isset($firstRoom['guests']) && is_array($firstRoom['guests'])) {
                    $firstGuest = reset($firstRoom['guests']);
                    if (is_array($firstGuest) && isset($firstGuest['checkin'])) {
                        return $firstGuest['checkin'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract check-out date from api_response or guests data.
     *
     * @param array<string, mixed> $apiResponse
     * @param array<string, mixed> $guests
     */
    private function parseCheckOutDate(array $apiResponse, array $guests): ?string
    {
        if (!empty($apiResponse) && isset($apiResponse['book_data']['check_out'])) {
            return $apiResponse['book_data']['check_out'];
        }

        if (!empty($guests)) {
            $firstRoom = reset($guests);
            
            if (is_array($firstRoom)) {
                if (isset($firstRoom['checkout'])) {
                    return $firstRoom['checkout'];
                }
                
                if (isset($firstRoom['guests']) && is_array($firstRoom['guests'])) {
                    $firstGuest = reset($firstRoom['guests']);
                    if (is_array($firstGuest) && isset($firstGuest['checkout'])) {
                        return $firstGuest['checkout'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract amenities from api_response if available, with fallback to star-rating based defaults.
     *
     * @param array<string, mixed> $apiResponse
     * @return array<string, mixed>
     */
    private function extractAmenities(array $apiResponse): array
    {
        $amenities = [];

        if (!empty($apiResponse) && isset($apiResponse['book_data'])) {
            $bookData = $apiResponse['book_data'];

            if (isset($bookData['services']) && is_array($bookData['services'])) {
                $amenities['services'] = $bookData['services'];
            }

            if (isset($bookData['room_amenities']) && is_array($bookData['room_amenities'])) {
                $amenities['room_amenities'] = $bookData['room_amenities'];
            }

            if (isset($bookData['hotel_amenities']) && is_array($bookData['hotel_amenities'])) {
                $amenities['hotel_amenities'] = $bookData['hotel_amenities'];
            }

            if (isset($bookData['included_services'])) {
                $amenities['included_services'] = $bookData['included_services'];
            }
        }

        if (empty($amenities) && $this->hotel) {
            $amenities = $this->getDefaultAmenitiesByStarRating($this->hotel->star_rating ?? 1);
        }

        return $amenities;
    }

    /**
     * Get default amenities based on hotel star rating.
     *
     * @param int $starRating
     * @return array<string, mixed>
     */
    private function getDefaultAmenitiesByStarRating(int $starRating): array
    {
        $baseAmenities = [
            'services' => ['24-Hour Front Desk', 'Daily Housekeeping'],
            'room_amenities' => ['Air Conditioning', 'Private Bathroom'],
        ];

        $starRatingAmenities = [
            1 => [
                'services' => ['24-Hour Front Desk'],
                'room_amenities' => ['Bed', 'Bathroom'],
            ],
            2 => [
                'services' => ['24-Hour Front Desk', 'Daily Housekeeping'],
                'room_amenities' => ['Air Conditioning', 'Private Bathroom', 'Bed Linens'],
            ],
            3 => [
                'services' => ['24-Hour Front Desk', 'Daily Housekeeping', 'Restaurant', 'Bar'],
                'room_amenities' => ['Air Conditioning', 'Private Bathroom', 'TV', 'Telephone', 'Bed Linens'],
                'hotel_amenities' => ['WiFi in Common Areas'],
            ],
            4 => [
                'services' => ['24-Hour Front Desk', 'Daily Housekeeping', 'Restaurant', 'Bar', 'Room Service', 'Concierge'],
                'room_amenities' => ['Air Conditioning', 'Private Bathroom', 'TV', 'Telephone', 'Bed Linens', 'Minibar', 'Work Desk'],
                'hotel_amenities' => ['Free WiFi', 'Business Center', 'Gym', 'Parking'],
            ],
            5 => [
                'services' => ['24-Hour Front Desk', 'Housekeeping', 'Multiple Restaurants', 'Bar & Lounge', 'Room Service', 'Concierge', 'Bellhop'],
                'room_amenities' => ['Air Conditioning', 'Private Bathroom', 'Flat-Screen TV', 'Telephone', 'Premium Bed Linens', 'Minibar', 'Work Desk', 'Bathrobes', 'Toiletries'],
                'hotel_amenities' => ['Free WiFi', 'Business Center', 'Fitness Center', 'Spa', 'Parking', 'Swimming Pool', 'Concierge Service'],
            ],
        ];

        return $starRatingAmenities[$starRating] ?? $baseAmenities;
    }
}
