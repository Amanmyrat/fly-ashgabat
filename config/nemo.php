<?php

$isLocalEnvironment = env('NEMO_ENV') === 'local';

return [
    'url' => $isLocalEnvironment ? env('NEMO_URL') : env('NEMO_URL_PRODUCTION'),
    'auth_token' => $isLocalEnvironment ? env('NEMO_AUTH_TOKEN') : env('NEMO_AUTH_TOKEN_PRODUCTION'),
    'user_id' => $isLocalEnvironment ? env('NEMO_USER_ID') : env('NEMO_USER_ID_PRODUCTION'),
    'tags' => $isLocalEnvironment ? explode(',', env('NEMO_REQUESTOR_TAGS')) : explode(',', env('NEMO_REQUESTOR_TAGS_PRODUCTION')),
];
