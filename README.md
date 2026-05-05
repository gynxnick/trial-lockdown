# Trial Lockdown

A tiny Pterodactyl/Pelican middleware that hardens a panel so it can
safely host **public trial credentials**. It blocks the requests a
shared trial user could use to lock everyone else out — password
change, email change, API key creation, 2FA toggles, SSH keys — plus
admin API access and destructive server actions. The rest of the
panel (browse, console, files, plugin install) stays fully usable.

It's a single PHP middleware, a single config file, and an install
script that wires both into your panel without touching anything
else.

## Install

```bash
curl -fsSL https://raw.githubusercontent.com/gynxnick/trial-lockdown/main/install.sh \
  | sudo bash -s -- --panel /var/www/pterodactyl
```

Flags:

| Flag | What it does |
|---|---|
| `--panel <path>` | **Required.** Pterodactyl/Pelican panel root. |
| `--disabled` | Install everything but leave the master switch off. Toggle later via `.env`. |
| `--skip-reload` | Don't try to reload `php-fpm`. Reload it yourself. |
| `--ref <branch>` | Pin to a non-default branch or tag. Default: `main`. |

The install is idempotent — re-running it is safe and won't duplicate
the Kernel.php registration. Backups are written next to every file
modified.

## What it blocks

| Endpoint | Why |
|---|---|
| `PUT/POST/PATCH /api/client/account/email` | Email change locks others out of the shared account. |
| `PUT/POST/PATCH /api/client/account/password` | Same — password change. |
| `POST   /api/client/account/api-keys` | Trial users shouldn't be able to extract long-lived API access. |
| `POST   /api/client/account/two-factor` | Enabling 2FA locks the shared creds. |
| `POST   /api/client/account/two-factor/disable` | Disabling 2FA mid-session would leave the next user without protection. |
| `POST   /api/client/account/ssh-keys`, `.../remove` | SSH keys persist across panel resets. |
| `*      /api/application/*` | Admin API — fully off-limits, defence-in-depth. |
| `DELETE /api/client/servers/*` | Server deletion. |
| `POST   /api/client/servers/*/settings/reinstall` | Reinstall is destructive. |
| `POST   /api/client/servers/*/settings/rename` | Renaming changes how every other trial user sees the server. |
| `POST   /api/client/servers/*/users` | Subuser invites would email random addresses. |
| `POST/GET/DELETE /api/client/servers/*/backups/*` | Backups can exfiltrate panel state via download. |

Read endpoints, console access, file viewing, server power actions
(start/stop/restart), and addon installs (Crate etc.) all pass
through unchanged.

The list lives in `config/trial-lockdown.php` after install — edit it
in place to add or remove patterns. Each entry is
`"<METHOD> <fnmatch-pattern>"` where METHOD is an HTTP verb or `*`
and the pattern is a path glob with no leading slash.

## Disable without uninstalling

```bash
sed -i 's/^TRIAL_LOCKDOWN_ENABLED=.*/TRIAL_LOCKDOWN_ENABLED=false/' /var/www/pterodactyl/.env
cd /var/www/pterodactyl && php artisan config:cache
```

## Uninstall

```bash
curl -fsSL https://raw.githubusercontent.com/gynxnick/trial-lockdown/main/uninstall.sh \
  | sudo bash -s -- --panel /var/www/pterodactyl
```

Reverses every change `install.sh` made — removes both files, strips
the Kernel.php block, removes the `.env` line, rebuilds the config
cache. Backups of the touched files are kept at
`Kernel.php.uninstall.bak` and `.env.uninstall.bak`.

## How it works

A single Laravel middleware (`Pterodactyl\Http\Middleware\TrialLockdown`)
registered as the first entry of the `api` middleware group. Every
inbound API request is checked against the blocked-pattern list; on
match the middleware short-circuits with a 403 in Pterodactyl's
standard error envelope. When the master switch is off the middleware
is a no-op — the early `enabled` check is the entire body of the
disabled path.

The frontend gets no patches. Buttons that hit blocked routes still
appear; clicking them surfaces the configured error message. That's
fine for a trial — the goal is "don't get locked out", not "perfect
UX for the locked-out actions".

## License

MIT — see [LICENSE](LICENSE).
