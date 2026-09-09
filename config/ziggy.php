<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Except
    |--------------------------------------------------------------------------
    |
    | Route names that should be excluded from the Ziggy payload shared with
    | the Inertia SPA. This prevents the frontend from accidentally resolving
    | API-only auth routes (like /api/login and /api/logout) when using the
    | web auth route names.
    |
    */

    'except' => [
        'login',
        'logout',
        // API-only auth flows that should not be used by the Inertia SPA.
        // Add any other API-only route names here if needed.
    ],

    /*
    |--------------------------------------------------------------------------
    | Groups
    |--------------------------------------------------------------------------
    |
    | Optional route groups for sharing different route sets with different
    | frontends. Not required for this fix.
    |
    */

    'groups' => [
        // 'web' => [],
        // 'api' => [],
    ],
];
