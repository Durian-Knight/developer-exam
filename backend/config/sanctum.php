<?php

// Only overrides the keys we care about; the rest is merged from Sanctum's defaults.
return [
    // Minutes until an issued API token is considered expired.
    'expiration' => env('SANCTUM_TOKEN_EXPIRATION') ? (int) env('SANCTUM_TOKEN_EXPIRATION') : null,
];
