<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сброс пароля</title>
</head>

<body style="font-family: 'Roboto', sans-serif; margin: 0; padding: 0; width: 100%; height: 100%;">

<!-- Main Wrapper Table -->
<table role="presentation" width="600" height="600" cellspacing="0" cellpadding="0" style="margin: 0 auto; background-color: #223A60; text-align: left; border-spacing: 0;">
    <thead>
    <tr>
        <td style="padding: 20px 40px; text-align: center; background-color: #223A60;">
            <!-- Logo -->
            <img src="https://flyashgabat.com:4443/assets/images/logo-white.png" alt="kupi.uz logo" width="100" style="display: block;">
        </td>
    </tr>
    </thead>

    <tbody>
    <tr>
        <td style="padding: 0 24px;">
            <!-- White Content Box with Border Radius -->
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 16px; padding: 20px;">
                <tr>
                    <td>
                        <h1 style="font-size: 26px; font-weight: 700; margin-top: 0; color: #1E2133;">Сброс пароля</h1>
                        <p style="font-size: 16px; font-weight: 400; color: #1E2133;">Вы направили запрос на восстановление пароля на сайте flyashgabat.com</p>
                        <p style="font-size: 16px; font-weight: 400; color: #1E2133;">Ваш код для сброса пароля:</p>

                        <!-- Code Box -->
                        <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 20px auto; text-align: center; border-spacing: 5px;">
                            <tr>
                                @foreach (str_split($code) as $digit)
                                    <td style="width: 38px; height: 50px; border: 1px solid #80849A; font-size: 24px; font-weight: bold; color: #1E2133; border-radius: 8px; padding: 10px;">{{ $digit }}</td>
                                @endforeach
                            </tr>
                        </table>

                        <!-- Expiration Notice -->
                        <p style="font-size: 16px; font-weight: 400; color: #1E2133;">Данный код станет неактивным через 60 минут.</p>

                        <!-- Signature -->
                        <p style="font-size: 16px; font-weight: 400; color: #1E2133;">С уважением,<br>Команда по поддержке клиентов flyashgabat.com</p>

                        <!-- Support Link -->
                        <p style="font-size: 14px; font-weight: 400; color: #80849A;">В случае, если вам не удается нажать кнопку «Активировать», то перейдите по ссылке <a href="https://flyashgabat.com" style="color: #3F5CFF; text-decoration: none;">https://flyashgabat.com/</a>.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    </tbody>

    <tfoot>
    <tr>
        <td style="padding: 0; text-align: center; background-color: #223A60;">
            <!-- Footer Image -->
            <img src="https://flyashgabat.com:4443/assets/images/footer-bg.png" alt="Footer image" style="width: 100%; display: block;">
        </td>
    </tr>
    </tfoot>
</table>

</body>

</html>
