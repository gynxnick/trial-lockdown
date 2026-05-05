#!/usr/bin/env bash
# Trial Lockdown — Pterodactyl trial-panel hardener
#
# Quick install:
#
#   curl -fsSL https://raw.githubusercontent.com/gynxnick/trial-lockdown/main/install.sh \
#     | sudo bash -s -- --panel /var/www/pterodactyl
#
# What it does, idempotently:
#   1. Drops src/TrialLockdown.php into <panel>/app/Http/Middleware/
#   2. Drops src/trial-lockdown.php into <panel>/config/
#   3. Patches <panel>/app/Http/Kernel.php — registers the middleware
#      as the first entry of the 'api' group, between
#      "// trial-lockdown:start" and "// trial-lockdown:end" markers
#   4. Sets TRIAL_LOCKDOWN_ENABLED=true in <panel>/.env
#   5. php artisan config:cache
#   6. Best-effort php-fpm reload
#
# Flags:
#   --panel <path>    Required. Pterodactyl/Pelican panel root
#   --disabled        Install but leave the master switch off
#   --skip-reload     Don't try to reload php-fpm
#   --ref <branch>    Pin to a non-default branch/tag (default: main)

set -euo pipefail

REPO="gynxnick/trial-lockdown"
REF="${TRIAL_LOCKDOWN_REF:-main}"

PANEL=""
ENABLED="true"
SKIP_RELOAD=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --panel=*)     PANEL="${1#*=}"; shift ;;
        --panel)       PANEL="$2"; shift 2 ;;
        --disabled)    ENABLED="false"; shift ;;
        --skip-reload) SKIP_RELOAD=1; shift ;;
        --ref=*)       REF="${1#*=}"; shift ;;
        --ref)         REF="$2"; shift 2 ;;
        *) echo "Unknown flag: $1" >&2; exit 64 ;;
    esac
done

NC='\033[0m'; BOLD='\033[1m'
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[0;33m'; CYAN='\033[0;36m'
say() { printf "${CYAN}>${NC} %s\n" "$1"; }
ok()  { printf "  ${GREEN}+${NC} %s\n" "$1"; }
warn(){ printf "  ${YELLOW}!${NC} %s\n" "$1"; }
die() { printf "  ${RED}x${NC} %s\n" "$1" >&2; exit 1; }

[[ -z "$PANEL" ]] && die "--panel <path> is required (e.g. /var/www/pterodactyl)"
[[ -d "$PANEL" ]] || die "Panel directory not found: $PANEL"
[[ -f "$PANEL/app/Http/Kernel.php" ]] || die "$PANEL doesn't look like a Pterodactyl/Pelican panel (no app/Http/Kernel.php)"

command -v curl >/dev/null || die "curl is required but not on PATH"
command -v php  >/dev/null || die "php is required but not on PATH"

RAW="https://raw.githubusercontent.com/${REPO}/${REF}"

# 1) Middleware
say "Installing TrialLockdown middleware"
mkdir -p "$PANEL/app/Http/Middleware"
curl -fsSL "$RAW/src/TrialLockdown.php" \
    -o "$PANEL/app/Http/Middleware/TrialLockdown.php"
ok "$PANEL/app/Http/Middleware/TrialLockdown.php"

# 2) Config
say "Installing config"
curl -fsSL "$RAW/src/trial-lockdown.php" \
    -o "$PANEL/config/trial-lockdown.php"
ok "$PANEL/config/trial-lockdown.php"

# 3) Patch Kernel.php — idempotent via markers
say "Registering middleware in app/Http/Kernel.php"
KERNEL="$PANEL/app/Http/Kernel.php"
if grep -q "trial-lockdown:start" "$KERNEL"; then
    ok "Middleware already registered (idempotent skip)"
else
    cp "$KERNEL" "$KERNEL.trial-lockdown.bak"
    php <<'PHP_PATCH' "$KERNEL"
<?php
$path = $argv[1];
$src = file_get_contents($path);

$insert = "\n            // trial-lockdown:start"
        . "\n            \\Pterodactyl\\Http\\Middleware\\TrialLockdown::class,"
        . "\n            // trial-lockdown:end";

// Insert as the first entry of the 'api' middleware group. Pattern
// matches both `'api' => [` and `"api" => [` with any whitespace.
$patched = preg_replace(
    '/([\'"]api[\'"]\s*=>\s*\[)/',
    '$1' . $insert,
    $src,
    1,
    $count
);
if ($count !== 1 || $patched === null) {
    fwrite(STDERR, "Could not locate 'api' middleware group in Kernel.php\n");
    exit(1);
}
file_put_contents($path, $patched);
PHP_PATCH
    ok "Added middleware to api group (backup at $KERNEL.trial-lockdown.bak)"
fi

# 4) .env
say "Setting TRIAL_LOCKDOWN_ENABLED=$ENABLED in .env"
ENV_FILE="$PANEL/.env"
if [[ -f "$ENV_FILE" ]]; then
    if grep -q "^TRIAL_LOCKDOWN_ENABLED=" "$ENV_FILE"; then
        cp "$ENV_FILE" "$ENV_FILE.bak"
        sed -i.tmp "s/^TRIAL_LOCKDOWN_ENABLED=.*/TRIAL_LOCKDOWN_ENABLED=$ENABLED/" "$ENV_FILE"
        rm -f "$ENV_FILE.tmp"
    else
        printf "\nTRIAL_LOCKDOWN_ENABLED=%s\n" "$ENABLED" >> "$ENV_FILE"
    fi
    ok "$ENV_FILE updated"
else
    warn ".env not found; set TRIAL_LOCKDOWN_ENABLED=$ENABLED manually"
fi

# 5) Rebuild config cache
say "Rebuilding config cache"
if (cd "$PANEL" && php artisan config:cache >/dev/null 2>&1); then
    ok "config:cache rebuilt"
else
    warn "config:cache failed - run 'php artisan config:cache' manually"
fi

# 6) Reload php-fpm
if [[ "$SKIP_RELOAD" -eq 0 ]] && command -v systemctl >/dev/null; then
    say "Reloading php-fpm"
    if   systemctl reload php-fpm     2>/dev/null; then ok "php-fpm reloaded"
    elif systemctl reload php8.3-fpm  2>/dev/null; then ok "php8.3-fpm reloaded"
    elif systemctl reload php8.2-fpm  2>/dev/null; then ok "php8.2-fpm reloaded"
    elif systemctl reload php8.1-fpm  2>/dev/null; then ok "php8.1-fpm reloaded"
    else warn "Could not auto-reload php-fpm - restart it manually"
    fi
fi

cat <<EOF

  ${BOLD}${GREEN}Trial Lockdown installed${NC}

  Status:    $([[ "$ENABLED" == "true" ]] && echo "ENABLED" || echo "DISABLED (config-set)")
  Panel:     $PANEL
  Toggle:    edit TRIAL_LOCKDOWN_ENABLED in $PANEL/.env, then
             cd $PANEL && php artisan config:cache

  Test:      log in as the trial user and try 'Change Password' -
             you should see "This action is disabled on the trial panel."

  Uninstall: curl -fsSL ${RAW}/uninstall.sh | sudo bash -s -- --panel $PANEL

EOF
