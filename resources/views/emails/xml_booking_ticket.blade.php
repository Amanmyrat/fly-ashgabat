<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вы оплатили заказ на Fly-Ashgabat!</title>
</head>

<body style="font-family: 'Roboto', sans-serif; margin: 0; padding: 0; width: 100%; height: 100%;">

@php
    use Carbon\Carbon;

    function fa_email_date(?string $value, string $format): string {
        if (!$value) {
            return '';
        }

        foreach (['d.m.Y H:i:s', 'd.m.Y H:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i:sP'] as $inputFormat) {
            try {
                return Carbon::createFromFormat($inputFormat, $value)->format($format);
            } catch (\Throwable $e) {
                //
            }
        }

        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    $outwardSegments = $flightData['Outward'] ?? [];
    $returnSegments = $flightData['Return'] ?? null;
@endphp

<table role="presentation" width="600" cellspacing="0" cellpadding="0"
       style="margin: 0 auto; background-color: #223A60; text-align: left; border-spacing: 0;">
    <thead>
    <tr>
        <td style="padding: 20px 40px; text-align: center; background-color: #223A60; opacity: 0.55;">
            <img src="https://flyashgabat.com:4443/assets/images/logo-white.png" alt="Logo" width="100"
                 style="display: block;">
        </td>
    </tr>
    </thead>

    <tbody>
    <tr>
        <td style="padding: 0 24px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                   style="background-color: #ffffff; border-radius: 16px; padding: 20px;">
                <tr>
                    <td>
                        <h1 style="font-size: 26px; font-weight: 700; margin-top: 0; color: #1E2133;">
                            Вы оплатили заказ на Fly-Ashgabat!
                        </h1>

                        <p style="font-size: 16px; font-weight: 400; color: #1E2133;">
                            Выписка по вашему заказу во вложении.
                        </p>

                        @if(!empty($bookingReference))
                            <p style="font-size: 14px; color: #1E2133;">
                                Номер заказа: <strong>{{ $bookingReference }}</strong>
                            </p>
                        @endif

                        @if(!empty($ticketNumber))
                            <p style="font-size: 14px; color: #1E2133;">
                                Номер билета: <strong>{{ $ticketNumber }}</strong>
                            </p>
                        @endif

                        @if(!empty($pnr))
                            <p style="font-size: 14px; color: #1E2133;">
                                PNR: <strong>{{ $pnr }}</strong>
                            </p>
                        @endif

                        @if(!empty($outwardSegments))
                            @php
                                $firstSegment = $outwardSegments[0];
                                $lastSegment = $outwardSegments[count($outwardSegments) - 1];
                            @endphp

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                                   style="background-color: #EFF5FB; border-radius: 16px; border: 1px solid #EFF5FB; margin-top: 20px;">
                                <tr>
                                    <td style="padding: 16px;">
                                        <table width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="font-size: 18px; font-weight: 600; color: #1E2133;">
                                                    <img src="https://kupi.abflow.uz:3456/images/plane.png"
                                                         alt="Departure"
                                                         style="vertical-align: middle; margin-right: 4px;">
                                                    {{ $firstSegment['Departure']['City'] ?? '' }},
                                                    {{ $firstSegment['Departure']['Airport'] ?? '' }}
                                                    <img src="https://kupi.abflow.uz:3456/images/arrow_right.png"
                                                         alt="Arrow"
                                                         style="vertical-align: middle; margin-right: 4px;">
                                                    {{ $lastSegment['Arrival']['City'] ?? '' }},
                                                    {{ $lastSegment['Arrival']['Airport'] ?? '' }}
                                                </td>
                                            </tr>
                                        </table>

                                        <table width="100%" cellspacing="0" cellpadding="5" style="margin-top: 10px;">
                                            <tr>
                                                <td style="color: #80849A; font-size: 14px;">Вылет</td>
                                                <td style="color: #80849A; font-size: 14px;">Время вылета</td>
                                                <td style="color: #80849A; font-size: 14px;">Дата вылета</td>
                                                <td style="color: #80849A; font-size: 14px;">Время прилёта</td>
                                                <td style="color: #80849A; font-size: 14px;">Дата прилёта</td>
                                                <td style="color: #80849A; font-size: 14px;">Рейс</td>
                                            </tr>

                                            @foreach($outwardSegments as $segment)
                                                <tr>
                                                    <td>
                                                        {{ $segment['Departure']['Code'] ?? '' }}
                                                        →
                                                        {{ $segment['Arrival']['Code'] ?? '' }}
                                                    </td>
                                                    <td style="font-size: 18px; font-weight: 600; color: #1E2133;">
                                                        {{ fa_email_date($segment['Departure']['Date'] ?? null, 'H:i') }}
                                                    </td>
                                                    <td>
                                                        {{ fa_email_date($segment['Departure']['Date'] ?? null, 'd M Y') }}
                                                    </td>
                                                    <td style="font-size: 18px; font-weight: 600; color: #1E2133;">
                                                        {{ fa_email_date($segment['Arrival']['Date'] ?? null, 'H:i') }}
                                                    </td>
                                                    <td>
                                                        {{ fa_email_date($segment['Arrival']['Date'] ?? null, 'd M Y') }}
                                                    </td>
                                                    <td style="font-size: 18px; font-weight: 600; color: #1E2133;">
                                                        {{ $segment['FlightNum'] ?? '' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>

                                @if(!empty($returnSegments))
                                    @php
                                        $firstSegment = $returnSegments[0];
                                        $lastSegment = $returnSegments[count($returnSegments) - 1];
                                    @endphp

                                    <tr>
                                        <td style="padding: 16px;">
                                            <table width="100%" cellspacing="0" cellpadding="0">
                                                <tr>
                                                    <td style="font-size: 18px; font-weight: 600; color: #1E2133;">
                                                        <img src="https://kupi.abflow.uz:3456/images/plane.png"
                                                             alt="Departure"
                                                             style="vertical-align: middle; margin-right: 4px;">
                                                        {{ $firstSegment['Departure']['City'] ?? '' }},
                                                        {{ $firstSegment['Departure']['Airport'] ?? '' }}
                                                        <img src="https://kupi.abflow.uz:3456/images/arrow_right.png"
                                                             alt="Arrow"
                                                             style="vertical-align: middle; margin-right: 4px;">
                                                        {{ $lastSegment['Arrival']['City'] ?? '' }},
                                                        {{ $lastSegment['Arrival']['Airport'] ?? '' }}
                                                    </td>
                                                </tr>
                                            </table>

                                            <table width="100%" cellspacing="0" cellpadding="5" style="margin-top: 10px;">
                                                <tr>
                                                    <td style="color: #80849A; font-size: 14px;">Вылет</td>
                                                    <td style="color: #80849A; font-size: 14px;">Время вылета</td>
                                                    <td style="color: #80849A; font-size: 14px;">Дата вылета</td>
                                                    <td style="color: #80849A; font-size: 14px;">Время прилёта</td>
                                                    <td style="color: #80849A; font-size: 14px;">Дата прилёта</td>
                                                    <td style="color: #80849A; font-size: 14px;">Рейс</td>
                                                </tr>

                                                @foreach($returnSegments as $segment)
                                                    <tr>
                                                        <td>
                                                            {{ $segment['Departure']['Code'] ?? '' }}
                                                            →
                                                            {{ $segment['Arrival']['Code'] ?? '' }}
                                                        </td>
                                                        <td style="font-size: 18px; font-weight: 600; color: #1E2133;">
                                                            {{ fa_email_date($segment['Departure']['Date'] ?? null, 'H:i') }}
                                                        </td>
                                                        <td>
                                                            {{ fa_email_date($segment['Departure']['Date'] ?? null, 'd M Y') }}
                                                        </td>
                                                        <td style="font-size: 18px; font-weight: 600; color: #1E2133;">
                                                            {{ fa_email_date($segment['Arrival']['Date'] ?? null, 'H:i') }}
                                                        </td>
                                                        <td>
                                                            {{ fa_email_date($segment['Arrival']['Date'] ?? null, 'd M Y') }}
                                                        </td>
                                                        <td style="font-size: 18px; font-weight: 600; color: #1E2133;">
                                                            {{ $segment['FlightNum'] ?? '' }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        @endif

                        <p>Спасибо за бронирование с Fly-Ashgabat!</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    </tbody>

    <tfoot>
    <tr>
        <td style="padding: 0; text-align: center; background-color: #223A60;">
            <img src="https://flyashgabat.com:4443/assets/images/footer-bg.png" alt="Footer"
                 style="width: 100%; display: block;">
        </td>
    </tr>
    </tfoot>
</table>

</body>
</html>
