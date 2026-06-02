<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'travelfusion' => [
        'base_url' => env('TRAVELFUSION_BASE_URL', 'https://api.travelfusion.com'),
        'username' => env('TRAVELFUSION_USERNAME'),
        'password' => env('TRAVELFUSION_PASSWORD'),
    ],

    'etg' => [
        'base_url'        => env('ETG_BASE_URL', 'https://api.worldota.net'),
        'username'        => env('ETG_USERNAME'),
        'password'        => env('ETG_PASSWORD'),
        'delete_archives' => env('ETG_DELETE_ARCHIVES', false),
        // Use LOAD DATA LOCAL INFILE for base imports (hotels/regions).
        // Requires local_infile=ON on the MySQL server. Typically 10-50x faster
        // for the DB write step. Keep false locally; enable on the server.
        'use_load_data'   => env('ETG_USE_LOAD_DATA', false),
    ],

];
