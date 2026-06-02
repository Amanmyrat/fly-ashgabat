<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Hotel booking confirmed') }}</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1a1a1a; max-width: 600px; margin: 0 auto; padding: 24px; background-color: #f9fafb;">
    <div style="background-color: #ffffff; border-radius: 8px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 8px; color: #1a1a1a;">{{ __('Hotel Booking Confirmed') }} ✅</h1>
        <p style="margin: 0 0 24px; color: #666; font-size: 0.9375rem;">{{ __('Thank you for your booking. Your reservation has been confirmed with the hotel.') }}</p>

        <div style="margin: 0 0 24px; padding: 16px; background: linear-gradient(135deg, #dcfce7 0%, #dbeafe 100%); border-left: 4px solid #059669; border-radius: 4px;">
            <p style="margin: 0; color: #15803d; font-weight: 600;">📎 {{ __('Your voucher is attached') }}</p>
            <p style="margin: 8px 0 0; color: #166534; font-size: 0.9375rem;">{{ __('Please save and present it at the hotel during check-in.') }}</p>
        </div>

        <h2 style="font-size: 1rem; font-weight: 600; margin: 24px 0 16px; color: #1a1a1a; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;">📋 {{ __('Booking Details') }}</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">

            <tr style="background-color: #f9fafb;">
                <td style="padding: 12px; color: #666; font-weight: 500; border-bottom: 1px solid #e5e7eb;">{{ __('Total Amount') }}</td>
                <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;"><strong style="font-size: 1.125rem; color: #059669;">{{ number_format((float) $booking->amount, 2) }} {{ $booking->currency }}</strong></td>
            </tr>
            <tr>
                <td style="padding: 12px; color: #666; font-weight: 500; border-bottom: 1px solid #e5e7eb;">{{ __('Payment Type') }}</td>
                <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
                    @php
                        $badgeColor = match($booking->payment_type) {
                            'stripe' => '#dbeafe',
                            'balance' => '#fecdd3',
                            'postpay' => '#fef3c7',
                            default => '#e5e7eb'
                        };
                        $textColor = match($booking->payment_type) {
                            'stripe' => '#1e40af',
                            'balance' => '#be123c',
                            'postpay' => '#92400e',
                            default => '#1a1a1a'
                        };
                    @endphp
                    <span style="display: inline-block; background-color: {{ $badgeColor }}; color: {{ $textColor }}; padding: 4px 12px; border-radius: 20px; font-size: 0.875rem; font-weight: 500;">{{ ucfirst($booking->payment_type) }}</span>
                </td>
            </tr>
            <tr style="background-color: #f9fafb;">
                <td style="padding: 12px; color: #666; font-weight: 500;">{{ __('Status') }}</td>
                <td style="padding: 12px;"><span style="display: inline-block; background-color: #dcfce7; color: #15803d; padding: 4px 12px; border-radius: 20px; font-size: 0.875rem; font-weight: 500;">{{ ucfirst($booking->status) }}</span></td>
            </tr>
        </table>

        @if($booking->hotel && ($booking->hotel->name ?? $booking->hotel->name_en))
        <h2 style="font-size: 1rem; font-weight: 600; margin: 24px 0 16px; color: #1a1a1a; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;">🏨 {{ $booking->hotel->name ?? $booking->hotel->name_en }}</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
            @if($booking->hotel->star_rating)
            <tr style="background-color: #f9fafb;">
                <td style="padding: 12px; color: #666; font-weight: 500; border-bottom: 1px solid #e5e7eb;">{{ __('Star Rating') }}</td>
                <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
                    @for ($i = 0; $i < $booking->hotel->star_rating; $i++)⭐@endfor
                </td>
            </tr>
            @endif
            <tr>
                <td style="padding: 12px; color: #666; font-weight: 500; border-bottom: 1px solid #e5e7eb;">{{ __('Address') }}</td>
                <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">{{ $booking->hotel->address ?? $booking->hotel->address_en ?? '—' }}</td>
            </tr>
            <tr style="background-color: #f9fafb;">
                <td style="padding: 12px; color: #666; font-weight: 500; border-bottom: 1px solid #e5e7eb;">{{ __('Room Type') }}</td>
                <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">{{ $booking->room_type ?? '—' }}</td>
            </tr>
            <tr>
                <td style="padding: 12px; color: #666; font-weight: 500;">{{ __('Guests') }}</td>
                <td style="padding: 12px;">
                    {{ $booking->adults_count ?? 0 }} 👤 Adult(s){{ $booking->children_count > 0 ? ', ' . $booking->children_count . ' 👶 Child(ren)' : '' }}
                    <span style="display: block; font-size: 0.875rem; color: #666; margin-top: 4px;">{{ $booking->rooms_count ?? 1 }} 🛏️ Room(s)</span>
                </td>
            </tr>
        </table>
        @endif

        <h2 style="font-size: 1rem; font-weight: 600; margin: 24px 0 16px; color: #1a1a1a; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;">👥 {{ __('Guest Information') }}</h2>
        <div style="margin-bottom: 24px;">
            @php
                $guestNumber = 1;
            @endphp
            @foreach($booking->guests as $room)
                @if(isset($room['guests']) && is_array($room['guests']))
                    @foreach($room['guests'] as $guest)
                        <div style="margin-bottom: 12px; padding: 12px; background-color: #f0f9ff; border-left: 3px solid #0284c7; border-radius: 4px;">
                            <div style="font-weight: 600; color: #0c4a6e; margin-bottom: 4px;">
                                {{ $guest['first_name'] ?? '' }} {{ $guest['last_name'] ?? '' }}
                            </div>

                        </div>
                        @php $guestNumber++; @endphp
                    @endforeach
                @endif
            @endforeach
        </div>

        <div style="margin-top: 32px; padding: 20px; background-color: #f0fdf4; border-radius: 6px; border-left: 4px solid #059669;">
            <p style="margin: 0 0 8px; font-weight: 600; color: #15803d;">✈️ {{ __('What happens next?') }}</p>
            <p style="margin: 0; color: #166534; font-size: 0.9375rem;">{{ __('The hotel will contact you to confirm final details. You can also reach out to our support team if you have any questions.') }}</p>
        </div>

        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb; font-size: 0.875rem; color: #666;">
            <p style="margin: 0 0 8px;">{{ __('Questions?') }} {{ __('If you have any questions about your booking, please reply to this email or contact our support team.') }}</p>
            <p style="margin: 0; color: #999;">{{ __('Contact phone:') }} <strong>+993 61 826345 | +993 71 064149</strong></p>
            <p style="margin: 0; color: #999;">{{ __('Keep this email for your records. Reference:') }} <strong>{{ $booking->partner_order_id }}</strong></p>
        </div>
    </div>
</body>
</html>
