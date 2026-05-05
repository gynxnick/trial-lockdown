<?php
/**
 * Removes the TrialLockdown registration block from
 * app/Http/Kernel.php (the region between // trial-lockdown:start
 * and // trial-lockdown:end markers).
 *
 * Usage:
 *   php strip-kernel.php /var/www/pterodactyl/app/Http/Kernel.php
 *
 * Run as a standalone CLI script — only manipulates the Kernel.php
 * text, never includes it.
 */

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php strip-kernel.php <path-to-Kernel.php>\n");
    exit(64);
}

$path = $argv[1];
if (!is_file($path)) {
    fwrite(STDERR, "Not a file: {$path}\n");
    exit(2);
}

$src = file_get_contents($path);
if ($src === false) {
    fwrite(STDERR, "Could not read {$path}\n");
    exit(2);
}

if (!str_contains($src, 'trial-lockdown:start')) {
    fwrite(STDERR, "No trial-lockdown markers in {$path} - nothing to strip.\n");
    exit(0);
}

$stripped = preg_replace(
    '/\s*\/\/ trial-lockdown:start.*?\/\/ trial-lockdown:end/s',
    '',
    $src
);

if ($stripped === null) {
    fwrite(STDERR, "Regex strip failed on {$path}\n");
    exit(1);
}

if (file_put_contents($path, $stripped) === false) {
    fwrite(STDERR, "Could not write {$path}\n");
    exit(2);
}

echo "Stripped.\n";
