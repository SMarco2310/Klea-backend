<?php

return [
    // e.g. https://your-app.clerk.accounts.dev/.well-known/jwks.json
    'jwks_url' => env('CLERK_JWKS_URL'),

    // Must match the "iss" claim on Clerk's session tokens for your instance.
    'issuer' => env('CLERK_ISSUER'),
];
