<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trial Lockdown — read-mostly hardener for public-trial Pterodactyl panels.
 *
 * When TRIAL_LOCKDOWN_ENABLED=true, walks config('trial-lockdown.blocked')
 * top-to-bottom. Each entry is either:
 *
 *   "<METHOD> <fnmatch-glob>"           block — return 403
 *   "allow <METHOD> <fnmatch-glob>"     allow — short-circuit through
 *
 * First match wins. So an `allow` line listed BEFORE a broader block
 * punches through, letting us keep coarse `DELETE api/client/servers/*`
 * rules while permitting specific sub-paths (e.g. addon installer
 * uninstalls). Entries without the `allow` prefix behave exactly as
 * they did in 1.0 — pure backward-compatible extension.
 *
 * Lines starting with '#' or blank lines are skipped. Malformed
 * entries (wrong token count) are silently ignored so a typo doesn't
 * lock the whole panel.
 *
 * Disabled by default — opt-in via .env.
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

            // Strip the optional "allow " prefix and remember whether
            // a match here should short-circuit through instead of
            // returning 403.
            $isAllow = false;
            if (preg_match('/^allow\s+/i', $entry)) {
                $isAllow = true;
                $entry = preg_replace('/^allow\s+/i', '', $entry, 1);
            }

            $parts = preg_split('/\s+/', $entry, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$ruleMethod, $rulePattern] = $parts;
            $ruleMethod = strtoupper($ruleMethod);

            if ($ruleMethod !== '*' && $ruleMethod !== $method) {
                continue;
            }
            if (!fnmatch($rulePattern, $path)) {
                continue;
            }

            // First match wins. Allow → pass through. Block → 403.
            if ($isAllow) {
                return $next($request);
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
