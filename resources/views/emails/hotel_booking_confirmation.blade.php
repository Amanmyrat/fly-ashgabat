<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Hotel booking') }}</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1a1a1a; max-width: 560px; margin: 0 auto; padding: 24px;">
    <h1 style="font-size: 1.25rem; font-weight: 600; margin: 0 0 16px;">{{ __('Your hotel booking') }}</h1>
    <p style="margin: 0 0 16px;">{{ __('Thank you. A PDF confirmation is attached when available. Keep this email for your records.') }}</p>
    <table style="width: 100%; border-collapse: collapse; font-size: 0.9375rem;">
        <tr><td style="padding: 6px 0; color: #555;">{{ __('Order reference') }}</td><td style="padding: 6px 0;"><strong>{{ $booking['partner_order_id'] ?? '—' }}</strong></td></tr>
        <tr><td style="padding: 6px 0; color: #555;">{{ __('Supplier order ID') }}</td><td style="padding: 6px 0;">{{ $booking['order_id'] ?? '—' }}</td></tr>
        <tr><td style="padding: 6px 0; color: #555;">{{ __('Status') }}</td><td style="padding: 6px 0;">{{ $booking['status'] ?? '—' }}</td></tr>
        <tr><td style="padding: 6px 0; color: #555;">{{ __('Amount') }}</td><td style="padding: 6px 0;">{{ $booking['amount'] ?? '' }} {{ $booking['currency'] ?? '' }}</td></tr>
    </table>
    <p style="margin: 24px 0 0; font-size: 0.875rem; color: #666;">{{ __('Use the reference above for support. Flight tickets are PDFs we generate from your booking; this email is your hotel confirmation summary.') }}</p>
</body>
</html>
