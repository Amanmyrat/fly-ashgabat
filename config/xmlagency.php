<?php

return [
    // API Endpoint
    'base_url' => env('XMLAGENCY_BASE_URL'),
    'search_endpoint' => '/SiteCity',

    // Credentials (from env)
    'credentials' => [
        'login' => env('XMLAGENCY_API_LOGIN'),
        'password' => env('XMLAGENCY_API_PASSWORD'),
    ],
    'device_id' => env('XMLAGENCY_DEVICE_ID'),

    // Basic settings
    'currency' => env('XMLAGENCY_CURRENCY', 'EUR'),
    'timeout' => env('XMLAGENCY_TIMEOUT', 60),

    // SOAP Actions
    'soap_actions' => [
        'AeroSearch' => 'http://tempuri.org/ISiteAvia/AeroSearch',
        'AeroBook' => 'http://tempuri.org/ISiteAvia/AeroBook',
        'ConfirmBook' => 'http://tempuri.org/ISiteBookInfo/ConfirmBook',
    ],
];
