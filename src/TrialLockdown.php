<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trial Lockdown — read-mostly hardener for public-trial Pterodactyl panels.
 *
 * When TRIAL_LOCKDOWN_ENABLED=true, returns 403 on requests matching any
 * "<METHOD> <fnmatch-glob>" entry in config('trial-lockdown.blocked').
 * Read paths and the "core" trial flow (browse, console, files, install
 * plugins) pass through unchanged. Disabled by default — opt-in via .env.
 */
class TrialLockdown
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('trial-lockdown.enabled', false)) {
            return $next($request);
        }

        $method = strtoupper($request->method());
        $path = trim($request->path(), '/');

        foreach ((array) config('trial-lockdown.blocked', []) as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '' || str_starts_with($entry, '#')) {
                continue;
            }

            $parts = preg_split('/\s+/', $entry, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$blockedMethod, $blockedPattern] = $parts;
            $blockedMethod = strtoupper($blockedMethod);

            if ($blockedMethod !== '*' && $blockedMethod !== $method) {
                continue;
            }
            if (!fnmatch($blockedPattern, $path)) {
                continue;
            }

            return new JsonResponse([
                'errors' => [[
                    'code' => 'TrialLockdownException',
                    'status' => '403',
                    'detail' => (string) config(
                        'trial-lockdown.message',
                        'This action is disabled on the trial panel.'
                    ),
                ]],
            ], 403);
        }

        return $next($request);
    }
}
