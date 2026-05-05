<?php
/**
 * Inserts the TrialLockdown middleware as the first entry of the
 * 'api' middleware group in Pterodactyl's app/Http/Kernel.php.
 *
 * Idempotent — bails early if the trial-lockdown:start marker is
 * already present.
 *
 * Usage:
 *   php patch-kernel.php /var/www/pterodactyl/app/Http/Kernel.php
 *
 * Run as a standalone CLI script (no Laravel bootstrap, no
 * autoloader) — only manipulates the Kernel.php text, never includes
 * it.
 */

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php patch-kernel.php <path-to-Kernel.php>\n");
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

if (str_contains($src, 'trial-lockdown:start')) {
    fwrite(STDERR, "Kernel.php already patched (idempotent skip).\n");
    exit(0);
}

$insert = "\n            // trial-lockdown:start"
        . "\n            \\Pterodactyl\\Http\\Middleware\\TrialLockdown::class,"
        . "\n            // trial-lockdown:end";

// Insert as the first entry of the 'api' middleware group. Pattern
// matches both `'api' => [` and `"api" => [` with any whitespace.
$count = 0;
$patched = preg_replace(
    '/([\'"]api[\'"]\s*=>\s*\[)/',
    '$1' . $insert,
    $src,
    1,
    $count
);

if ($count !== 1 || $patched === null) {
    fwrite(STDERR, "Could not locate 'api' middleware group in {$path}\n");
    exit(1);
}

if (file_put_contents($path, $patched) === false) {
    fwrite(STDERR, "Could not write {$path}\n");
    exit(2);
}

echo "Patched.\n";
