<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | Set TRIAL_LOCKDOWN_ENABLED=true in .env to activate. Off by default
    | so installing the middleware doesn't break anyone's panel by accident.
    */
    'enabled' => env('TRIAL_LOCKDOWN_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | 403 detail message
    |--------------------------------------------------------------------------
    | Shown to the trial user when an action is blocked. Plain text only.
    */
    'message' => env(
        'TRIAL_LOCKDOWN_MESSAGE',
        'This action is disabled on the trial panel.'
    ),

    /*
    |--------------------------------------------------------------------------
    | Blocked routes
    |--------------------------------------------------------------------------
    | Format: "<METHOD> <fnmatch-pattern>"
    |   METHOD          HTTP verb (GET/POST/PUT/DELETE/PATCH) or '*' for any
    |   fnmatch-pattern Path pattern with no leading slash, '*' wildcard
    |
    | Lines starting with '#' are treated as comments and skipped.
    */
    'blocked' => [

        // ── Account mutation (locks shared trial creds) ──────────────────
        // Defensive wildcard: every non-GET under /api/client/account/*
        // is blocked. Catches email, password, 2FA (any path variant),
        // api-keys, ssh-keys, and any new account-mutation route a
        // future Pterodactyl/Pelican/forked version adds — without
        // having to chase HTTP-verb and slug differences. GETs (read
        // profile, list api keys, etc.) pass through.
        'PUT    api/client/account/*',
        'POST   api/client/account/*',
        'PATCH  api/client/account/*',
        'DELETE api/client/account/*',

        // ── Admin API — block entirely (in case trial user is escalated)
        '*      api/application/*',

        // ── Destructive server actions ──────────────────────────────────
        'DELETE api/client/servers/*',
        'POST   api/client/servers/*/settings/reinstall',
        'POST   api/client/servers/*/settings/rename',
        'POST   api/client/servers/*/users',
        'PATCH  api/client/servers/*/users/*',
        'DELETE api/client/servers/*/users/*',

        // ── Backups (trial users can't download .tar.gz of arbitrary state)
        'POST   api/client/servers/*/backups',
        'GET    api/client/servers/*/backups/*/download',
        'POST   api/client/servers/*/backups/*/restore',
        'DELETE api/client/servers/*/backups/*',

    ],
];
