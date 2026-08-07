<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
    | Dashboard routes (auth:sanctum) are only ever called from our own
    | frontend, so we lock those origins down explicitly.
    |
    | Public/integration routes (api.key middleware, /api/public/*) are
    | meant to be called by third-party developers' own backends. CORS is
    | a browser-only mechanism — server-to-server calls from an
    | integrator's backend are never subject to it — so restricting
    | origins here does not block legitimate server-side integrations. It
    | only prevents arbitrary browser JS from calling us directly, which
    | is desirable since a public endpoint call requires an api key that
    | should never be embedded in client-side JS anyway.
    */
    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', '')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
