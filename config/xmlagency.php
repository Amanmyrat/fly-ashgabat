<?php

return [
    // API Endpoints - Updated to support dual URLs as per review
    'search_url' => env('XMLAGENCY_SEARCH_URL', 'http://search-api.xml.agency'),
    'main_url' => env('XMLAGENCY_MAIN_URL', 'http://api.city.travel'),
    'endpoint' => '/SiteCity',

    // Credentials (from env)
    'credentials' => [
        'login' => env('XMLAGENCY_API_LOGIN'),
        'password' => env('XMLAGENCY_API_PASSWORD'),
    ],
    'device_id' => env('XMLAGENCY_DEVICE_ID'),
    'token_guid' => env('XMLAGENCY_TOKEN_GUID', '00000000-0000-0000-0000-000000000000'),

    // Basic settings
    'currency' => env('XMLAGENCY_CURRENCY', 'EUR'),
    'timeout' => env('XMLAGENCY_TIMEOUT', 60),

    // SOAP Actions
    'soap_actions' => [
        'AeroSearch' => 'http://tempuri.org/ISiteAvia/AeroSearch',
        'AeroBook' => 'http://tempuri.org/ISiteAvia/AeroBook',
        'ConfirmBook' => 'http://tempuri.org/ISiteBookInfo/ConfirmBook',
        'OrderInfo' => 'http://tempuri.org/ISiteBookInfo/OrderInfo',
        'AeroPrebook' => 'http://tempuri.org/ISiteAvia/AeroPrebook',
    ],
];
