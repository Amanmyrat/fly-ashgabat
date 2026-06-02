<?php

$isLocalEnvironment = env('MYAGENT_ENV') === 'local';

return [
    'base_url' => $isLocalEnvironment
        ? env('MYAGENT_BASE_URL')
        : env('MYAGENT_BASE_URL_PRODUCTION'),

    'login' => $isLocalEnvironment
        ? env('MYAGENT_LOGIN')
        : env('MYAGENT_LOGIN_PRODUCTION'),

    'password' => $isLocalEnvironment
        ? env('MYAGENT_PASSWORD')
        : env('MYAGENT_PASSWORD_PRODUCTION'),

    'timeout' => (int) env('MYAGENT_TIMEOUT', 60),

    'user_agent' => $isLocalEnvironment
        ? env('MYAGENT_USER_AGENT', 'PetekBackend/1.0')
        : env('MYAGENT_USER_AGENT_PRODUCTION', 'PetekBackend/1.0'),

    'lang' => env('MYAGENT_LANG', 'ru'),

    'currency' => env('MYAGENT_CURRENCY', 'USD'),

    'cache' => [
        'auth_token_key' => $isLocalEnvironment
            ? 'myagent_auth_token_local'
            : 'myagent_auth_token_production',

        'auth_token_ttl' => (int) env('MYAGENT_AUTH_TOKEN_TTL', 55 * 60),
    ],
];
