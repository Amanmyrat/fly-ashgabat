<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вы оплатили заказ на Fly-Ashgabat!</title>
</head>

<body style="font-family: 'Roboto', sans-serif; margin: 0; padding: 0; width: 100%; height: 100%;">

<!-- Main Wrapper Table -->
<table role="presentation" width="600" height="600" cellspacing="0" cellpadding="0"
       style="margin: 0 auto; background-color: #223A60; text-align: left; border-spacing: 0; ">
    <thead>
    <tr>
        <td style="padding: 20px 40px; text-align: center; background-color: #223A60; opacity: 0.55; display: flex; justify-content: start;">
            <!-- Logo -->
            <!-- <img src="logo.png" alt="kupi.uz logo" width="100" style="display: block;"> -->

            <img src="https://flyashgabat.com:4443/assets/images/logo-white.png" alt="Logo" width="100"
                 style="display: block;">
        </td>
    </tr>
    </thead>

    <tbody>
    <tr>
        <td style="padding: 0 24px;">
            <!-- White Content Box with Border Radius -->
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                   style="background-color: #ffffff; border-radius: 16px; padding: 20px;">
                <tr>
                    <td>
                        <h1 style="font-size: 26px; font-weight: 700; margin-top: 0; color: #1E2133;">Вы
                            оплатили заказ
                            на Fly-Ashgabat!</h1>
                        <p style="font-size: 16px; font-weight: 400; color: #1E2133;">Выписка по вашему заказу
                            во
                            вложении.</p>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                               style="background-color: #EFF5FB; border-radius: 16px; border: 1px solid #EFF5FB; margin-top: 20px;">
                            <tr>
                                <td style="padding: 16px;">
                                    <table width="100%" cellspacing="0" cellpadding="0">
                                        <tr>
                                            @php
                                                use Carbon\Carbon;
                                                $firstSegment = $flightData['Outward'][0];
                                                $lastSegment = end($flightData['Outward']);
                                            @endphp
                                            <td style="font-size: 18px; font-weight: 600; color: #1E2133;">
                                                <img src="https://kupi.abflow.uz:3456/images/plane.png"
                                                     alt="Departure"
                                                     style="vertical-align: middle; margin-right: 4px;">
                                                {{ $firstSegment['Departure']['City'] }}
                                                , {{ $firstSegment['Departure']['Airport'] }}
                                                <img src="https://kupi.abflow.uz:3456/images/arrow_right.png"
                                                     alt="Arrow"
                                                     style="vertical-align: middle; margin-right: 4px;">
                                                {{ $lastSegment['Arrival']['City'] }}
                                                , {{ $lastSegment['Arrival']['Airport'] }}
                                            </td>
                                        </tr>
                                    </table>

                                    <table width="100%" cellspacing="0" cellpadding="5" style="margin-top: 10px;">
                                        <!-- Header Row -->
                                        <tr>
                                            <td style="color: #80849A; font-family: Roboto, sans-serif; font-size: 14px; font-weight: 400; text-align: left;">
                                                Вылет
                                            </td>
                                            <td style="color: #80849A; font-family: Roboto, sans-serif; font-size: 14px; font-weight: 400; text-align: left;">
                                                Время вылета
                                            </td>
                                            <td style="color: #80849A; font-family: Roboto, sans-serif; font-size: 14px; font-weight: 400; text-align: left;">
                                                Дата вылета
                                            </td>
                                            <td style="color: #80849A; font-family: Roboto, sans-serif; font-size: 14px; font-weight: 400; text-align: left;">
                                                Время прилёта
                                            </td>
                                            <td style="color: #80849A; font-family: Roboto, sans-serif; font-size: 14px; font-weight: 400; text-align: left;">
                                                Дата прилёта
                                            </td>
                                            <td style="color: #80849A; font-family: Roboto, sans-serif; font-size: 14px; font-weight: 400; text-align: left;">
                                                Рейс
                                            </td>
                                        </tr>

                                        @php
                                            $segments = $flightData['Outward'];
                                        @endphp
                                        @foreach($segments as $segment)
                                            <tr>
                                                <td>{{ $segment['Departure']['Code'] }}
                                                    → {{ $segment['Arrival']['Code'] }}</td>
                                                <td style="font-size: 18px; font-weight: 600; color: #1E2133;">{{ Carbon::createFromFormat('d.m.Y H:i', $segment['Departure']['Date'])->format('H:i') }}</td>
                                                <td>{{ Carbon::createFromFormat('d.m.Y H:i', $segment['Departure']['Date'])->format('d M Y') }}</td>
                                                <td style="font-size: 18px; font-weight: 600; color: #1E2133;">{{ Carbon::createFromFormat('d.m.Y H:i', $segment['Arrival']['Date'])->format('H:i') }}</td>
                                                <td>{{ Carbon::createFromFormat('d.m.Y H:i', $segment['Arrival']['Date'])->format('d M Y') }}</td>
                                                <td style="font-size: 18px; font-weight: 600; color: #1E2133;">{{ $segment['FlightNum'] }}</td>
                                            </tr>
                                        @endforeach
                                    </table>

                                </td>
                            </tr>
                            @if(isset($flightData['Return']))
                                <tr>
                                    <td style="padding: 16px;">
                                        <table width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                @php
                                                    $firstSegment = $flightData['Return'][0];
                                                    $lastSegment = end($flightData['Return']);
                                                @endphp
                                                <td style="font-size: 18px; font-weight: 600; color: #1E2133;">
                                                    <img src="https://kupi.abflow.uz:3456/images/plane.png"
                                                         alt="Departure"
                                                         style="vertical-align: middle; margin-right: 4px;">
                                                    {{ $firstSegment['Departure']['City'] }}
                                                    , {{ $firstSegment['Departure']['Airport'] }}
                                                    <img src="https://kupi.abflow.uz:3456/images/arrow_right.png"
                                                         alt="Arrow"
                                                         style="vertical-align: middle; margin-right: 4px;">
                                                    {{ $lastSegment['Arrival']['City'] }}
                                                    , {{ $lastSegment['Arrival']['Airport'] }}
                                                </td>
                                            </tr>
                                        </table>

                                        <table width="100%" cellspacing="0" cellpadding="5" style="margin-top: 10px;">
                                            <!-- Header Row -->
                                            <tr>
                                                <td style="color: #80849A; font-family: Roboto, sans-serif; font-size: 14px; font-weight: 400; text-align: left;">
                                                    Вылет
                                                </td>
                                                <td style="color: #80849A; font-family: Roboto, sans-serif; font-size: 14px; font-weight: 400; text-align: left;">
                                                    Время вылета
                                                </td>
                                                <td style="color: #80849A; font-family: Roboto, sans-serif; font-size: 14px; font-weight: 400; text-align: left;">
                                                    Дата вылета
                                                </td>
                                                <td style="color: #80849A; font-family: Roboto, sans-serif; font-size: 14px; font-weight: 400; text-align: left;">
                                                    Время прилёта
                                                </td>
                                                <td style="color: #80849A; font-family: Roboto, sans-serif; font-size: 14px; font-weight: 400; text-align: left;">
                                                    Дата прилёта
                                                </td>
                                                <td style="color: #80849A; font-family: Roboto, sans-serif; font-size: 14px; font-weight: 400; text-align: left;">
                                                    Рейс
                                                </td>
                                            </tr>

                                            @php
                                                $segments = $flightData['Return'];
                                            @endphp
                                            @foreach($segments as $segment)
                                                <tr>
                                                    <td>{{ $segment['Departure']['Code'] }}
                                                        → {{ $segment['Arrival']['Code'] }}</td>
                                                    <td style="font-size: 18px; font-weight: 600; color: #1E2133;">{{ Carbon::createFromFormat('d.m.Y H:i', $segment['Departure']['Date'])->format('H:i') }}</td>
                                                    <td>{{ Carbon::createFromFormat('d.m.Y H:i', $segment['Departure']['Date'])->format('d M Y') }}</td>
                                                    <td style="font-size: 18px; font-weight: 600; color: #1E2133;">{{ Carbon::createFromFormat('d.m.Y H:i', $segment['Arrival']['Date'])->format('H:i') }}</td>
                                                    <td>{{ Carbon::createFromFormat('d.m.Y H:i', $segment['Arrival']['Date'])->format('d M Y') }}</td>
                                                    <td style="font-size: 18px; font-weight: 600; color: #1E2133;">{{ $segment['FlightNum'] }}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                        </table>
                                    </td>
                                </tr>
                            @endif
                        </table>

                        <!-- Signature -->

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
