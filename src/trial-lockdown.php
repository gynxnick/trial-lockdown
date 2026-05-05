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
        // Email — vanilla Pterodactyl uses PUT; some forks POST/PATCH.
        'PUT    api/client/account/email',
        'POST   api/client/account/email',
        'PATCH  api/client/account/email',

        // Password — same pattern as email.
        'PUT    api/client/account/password',
        'POST   api/client/account/password',
        'PATCH  api/client/account/password',

        // API keys
        'POST   api/client/account/api-keys',
        'DELETE api/client/account/api-keys/*',

        // 2FA — vanilla Pterodactyl uses two-factor (hyphen), POST.
        // The /disable sub-route is a separate POST. Block both. Also
        // include two_factor (underscore) — older Pterodactyl + some
        // derivatives — and PUT/DELETE on the base path defensively.
        'POST   api/client/account/two-factor',
        'POST   api/client/account/two-factor/disable',
        'PUT    api/client/account/two-factor',
        'DELETE api/client/account/two-factor',
        'POST   api/client/account/two_factor',
        'POST   api/client/account/two_factor/disable',
        'PUT    api/client/account/two_factor',
        'DELETE api/client/account/two_factor',

        // SSH keys (path varies by version)
        'POST   api/client/account/ssh-keys',
        'POST   api/client/account/ssh-keys/remove',
        'POST   api/client/account/sshkeys',

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
