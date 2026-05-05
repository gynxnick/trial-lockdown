#!/usr/bin/env bash
# Trial Lockdown — uninstaller.
#
# Reverses every change install.sh made:
#   - removes the middleware + config files
#   - strips the registration block from Kernel.php
#   - strips TRIAL_LOCKDOWN_ENABLED from .env
#   - rebuilds the config cache
#
# Quick uninstall:
#
#   curl -fsSL https://raw.githubusercontent.com/gynxnick/trial-lockdown/main/uninstall.sh \
#     | sudo bash -s -- --panel /var/www/pterodactyl

set -euo pipefail

REPO="gynxnick/trial-lockdown"
REF="${TRIAL_LOCKDOWN_REF:-main}"

PANEL=""
SKIP_RELOAD=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --panel=*)     PANEL="${1#*=}"; shift ;;
        --panel)       PANEL="$2"; shift 2 ;;
        --skip-reload) SKIP_RELOAD=1; shift ;;
        --ref=*)       REF="${1#*=}"; shift ;;
        --ref)         REF="$2"; shift 2 ;;
        *) echo "Unknown flag: $1" >&2; exit 64 ;;
    esac
done

RAW="https://raw.githubusercontent.com/${REPO}/${REF}"

NC='\033[0m'; BOLD='\033[1m'
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[0;33m'; CYAN='\033[0;36m'
say() { printf "${CYAN}>${NC} %s\n" "$1"; }
ok()  { printf "  ${GREEN}+${NC} %s\n" "$1"; }
warn(){ printf "  ${YELLOW}!${NC} %s\n" "$1"; }
die() { printf "  ${RED}x${NC} %s\n" "$1" >&2; exit 1; }

[[ -z "$PANEL" ]] && die "--panel <path> is required"
[[ -d "$PANEL" ]] || die "Panel directory not found: $PANEL"
command -v curl >/dev/null || die "curl is required but not on PATH"
command -v php  >/dev/null || die "php is required but not on PATH"

# 1) Strip Kernel.php registration
KERNEL="$PANEL/app/Http/Kernel.php"
if [[ -f "$KERNEL" ]] && grep -q "trial-lockdown:start" "$KERNEL"; then
    say "Removing middleware registration from Kernel.php"
    cp "$KERNEL" "$KERNEL.uninstall.bak"
    STRIPPER="$(mktemp)"
    trap 'rm -f "$STRIPPER"' EXIT
    curl -fsSL "$RAW/tools/strip-kernel.php" -o "$STRIPPER"
    if php "$STRIPPER" "$KERNEL"; then
        ok "Kernel.php cleaned (backup at $KERNEL.uninstall.bak)"
    else
        die "Kernel.php strip failed - restore from $KERNEL.uninstall.bak"
    fi
else
    warn "No trial-lockdown markers in Kernel.php - nothing to strip"
fi

# 2) Remove middleware + config
[[ -f "$PANEL/app/Http/Middleware/TrialLockdown.php" ]] \
    && rm -f "$PANEL/app/Http/Middleware/TrialLockdown.php" \
    && ok "Removed app/Http/Middleware/TrialLockdown.php"

[[ -f "$PANEL/config/trial-lockdown.php" ]] \
    && rm -f "$PANEL/config/trial-lockdown.php" \
    && ok "Removed config/trial-lockdown.php"

# 3) Strip .env line
ENV_FILE="$PANEL/.env"
if [[ -f "$ENV_FILE" ]] && grep -q "^TRIAL_LOCKDOWN_ENABLED=" "$ENV_FILE"; then
    cp "$ENV_FILE" "$ENV_FILE.uninstall.bak"
    sed -i.tmp '/^TRIAL_LOCKDOWN_ENABLED=/d' "$ENV_FILE"
    rm -f "$ENV_FILE.tmp"
    ok "Removed TRIAL_LOCKDOWN_ENABLED from .env"
fi

# 4) Rebuild config cache
say "Rebuilding config cache"
if (cd "$PANEL" && php artisan config:cache >/dev/null 2>&1); then
    ok "config:cache rebuilt"
else
    warn "config:cache failed - run 'php artisan config:cache' manually"
fi

# 5) Reload php-fpm
if [[ "$SKIP_RELOAD" -eq 0 ]] && command -v systemctl >/dev/null; then
    say "Reloading php-fpm"
    if   systemctl reload php-fpm     2>/dev/null; then ok "php-fpm reloaded"
    elif systemctl reload php8.3-fpm  2>/dev/null; then ok "php8.3-fpm reloaded"
    elif systemctl reload php8.2-fpm  2>/dev/null; then ok "php8.2-fpm reloaded"
    elif systemctl reload php8.1-fpm  2>/dev/null; then ok "php8.1-fpm reloaded"
    else warn "Could not auto-reload php-fpm - restart it manually"
    fi
fi

echo ""
printf "  ${BOLD}${GREEN}Trial Lockdown uninstalled cleanly.${NC}\n"
echo ""
