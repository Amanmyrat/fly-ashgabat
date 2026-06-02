<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Hotel confirmation') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; padding: 24px; line-height: 1.5; }
        h1 { font-size: 18px; margin-bottom: 16px; font-weight: 600; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .meta td { padding: 6px 8px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
        .meta td:first-child { width: 38%; color: #555; }
        .section { margin-top: 16px; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #444; }
        .guest { margin-top: 8px; padding: 8px; background: #f7f7f7; border-radius: 4px; font-size: 11px; }
        .muted { font-size: 10px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>{{ __('Hotel booking confirmation') }}</h1>
    <table class="meta">
        <tr><td>{{ __('Status') }}</td><td>{{ $booking->status }}</td></tr>
        <tr><td>{{ __('Amount') }}</td><td>{{ number_format((float) $booking->amount, 2) }} {{ $booking->currency }}</td></tr>
        <tr><td>{{ __('Payment') }}</td><td>{{ $booking->payment_type }}</td></tr>
        <tr><td>{{ __('Contact') }}</td><td>{{ $booking->contact_email }}<br>{{ $booking->contact_phone }}</td></tr>
        <tr><td>{{ __('Contact phone') }}</td><td>+993 61 826345<br>+993 71 064149</td></tr>
    </table>

    <div class="section">{{ __('Guests') }}</div>
    @php $rooms = $booking->guests ?? []; @endphp
    @foreach($rooms as $i => $room)
        @foreach($room['guests'] ?? [] as $g)
            <div class="guest">
                {{ ($g['first_name'] ?? '') }} {{ ($g['last_name'] ?? '') }}
            </div>
        @endforeach
    @endforeach

    <p class="muted">{{ __('This document was issued by :app. Present the order reference if you need support.', ['app' => config('app.name')]) }}</p>
</body>
</html>
