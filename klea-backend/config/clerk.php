<?php

return [
    // e.g. https://your-app.clerk.accounts.dev/.well-known/jwks.json
    'jwks_url' => env('CLERK_JWKS_URL'),

    // Must match the "iss" claim on Clerk's session tokens for your instance.
    'issuer' => env('CLERK_ISSUER'),

    // Backend API secret key (sk_...) — used to look up a Clerk user's email
    // by id after verifying their session token, since the token itself
    // doesn't carry email/name claims by default.
    'secret_key' => env('CLERK_SECRET_KEY'),
];
