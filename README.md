# Paragon HostOps

**Hosting Management & Operations Platform** — an internal dashboard for
The Paragon Design that connects securely (read-only) to a WHM/cPanel server
via the WHM API 1 and surfaces hosting accounts, packages, SSL, bandwidth,
clients and financials.

> **Version 1 is read-only.** No destructive WHM actions exist (no terminate,
> suspend, password change, DNS edit, package delete, token management, or
> shell). The architecture is modular so controlled management features can be
> added later.

---

## 1. Technology

- PHP 8.2+ (tested on 8.4), MySQL 8 / MariaDB 10.4+
- PDO with prepared statements, PHP cURL for WHM
- Custom lightweight MVC-inspired architecture (no framework, no Node runtime,
  no Docker required in production)
- Bespoke, self-hosted CSS/JS front end. Chart.js 4.4 (MIT) is vendored locally
  at `public/assets/js/chart.umd.js` — nothing loads from a CDN, so a strict
  Content-Security-Policy (`script-src 'self'`) is enforced. Dashboard chart
  data is passed to Chart.js via a JSON data island, never inline script.

## 2. Directory layout

```
app/            Controllers, Services, Repositories, Middleware, Validators, Views, Core
bootstrap/      Container wiring + kernel bootstrap
config/         app.php, database.php, whm.php
database/       schema.sql, migrate.php, migrations/, seeds/
public/         Front controller (document root), assets, .htaccess
routes/         web.php, api.php
storage/        cache/, logs/, sessions/  (writable, web-inaccessible)
tests/          PHPUnit tests + WHM fixtures
bin/            create-admin.php (one-time)
```

## 3. Local development

```bash
composer install
cp .env.example .env         # then edit values
php database/migrate.php --seed
php bin/create-admin.php     # creates the first Super Administrator
composer serve               # http://localhost:8000
```

Run the tests:

```bash
composer test
```

### Mock mode (no live WHM needed)

Set `WHM_MOCK_MODE=true` in `.env`. The client reads canned fixtures from
`tests/fixtures/whm` instead of calling the server. **Never enable this in
production.**

## 4. Environment configuration

Copy `.env.example` to `.env` and fill in the values. The WHM API token lives
**only** in `.env` — it is never rendered in the UI, exposed to JavaScript,
returned in API responses, written to logs, or stored in the database.

Key groups: `APP_*`, `DB_*`, `WHM_*`, `SESSION_*`, `CRON_SECRET`, login
throttling. See the annotated `.env.example`.

## 5. WHM token configuration

1. In WHM → *Manage API Tokens*, create a **read-only** token with (at minimum)
   the privileges: `list-accts`, `acct-summary`, `list-pkgs`, `show-bandwidth`,
   `ssl-info`, `basic-system-info`, `basic-whm-functions`, `mysql-info`.
2. Put the host, username and token into `.env` (`WHM_HOST`, `WHM_USERNAME`,
   `WHM_API_TOKEN`). Do **not** include the protocol or port in `WHM_HOST`.
3. Visit **Settings → WHM** in the app and click **Test WHM connection**.

### Rotating / revoking the token

- **Rotate:** create a new token in WHM, update `WHM_API_TOKEN` in `.env`, then
  re-run the connection test. Finally delete the old token in WHM.
- **Revoke (compromise):** delete the token in WHM immediately, then replace the
  value in `.env`. The app fails closed — WHM features show
  "Unavailable with current WHM permissions" until a working token is set.

## 6. cPanel deployment

**Preferred:** point the account/subdomain document root at the project's
`public/` directory (cPanel → *Domains* → set document root, or place the app
outside `public_html` and repoint).

**If the document root cannot be moved** (basic shared hosting), upload the
whole project into `public_html`. The root `.htaccess` blocks direct access to
`app/`, `config/`, `database/`, `storage/`, `vendor/`, `.env`, and log/sql
files, and transparently rewrites requests into `public/`.

Steps:

1. Upload via File Manager / FTP / Git.
2. Create a MySQL database + user in cPanel; grant all privileges.
3. Set `.env` with the DB and WHM values.
4. Import `database/schema.sql` via phpMyAdmin **or** run
   `php database/migrate.php --seed` from cPanel *Terminal*.
5. Run `php bin/create-admin.php`, then **delete** `bin/create-admin.php`.
6. Ensure `storage/` is writable (0750/0770 depending on host).

### File permissions

- Directories `755` (or `750`), files `644`.
- `storage/` and its subdirectories must be writable by PHP.
- `.env` should be `600` where the host allows it.

### SSL

Serve the dashboard over HTTPS only. In production (`APP_ENV=production`) the
app sends HSTS and defaults `SESSION_SECURE_COOKIE` to on (override in `.env`
only for a plain-HTTP staging box).

## 7. Cron Jobs

Synchronisation and uptime checks run via a CLI script **or** a
secret-protected web endpoint (`public/cron.php`, guarded by `CRON_SECRET`).
No permanent worker is required. Example cPanel cron entries:

```
# Nightly WHM synchronisation (CLI) — also refreshes domain expiry dates.
# Add --no-domains to skip the domain lookups.
0 2 * * * /usr/local/bin/php /home/USER/hostops/bin/sync.php >/dev/null 2>&1

# Uptime checks every 10 minutes (CLI)
*/10 * * * * /usr/local/bin/php /home/USER/hostops/bin/uptime.php >/dev/null 2>&1

# Same jobs via the web endpoint (when CLI cron is unavailable):
#   curl -s "https://ops.example.com/cron.php?job=sync&token=YOUR_CRON_SECRET"
#   curl -s "https://ops.example.com/cron.php?job=uptime&token=YOUR_CRON_SECRET"
#   curl -s "https://ops.example.com/cron.php?job=domains&token=YOUR_CRON_SECRET"
```

**Alerts (email + Telegram):** enable delivery with `ALERTS_ENABLED=true`, then
configure one or both channels. Alerts fire for SSL and domain expiry (30/15/5
days, and expired) and disk/bandwidth usage (80%, then 95%). Each threshold
sends **once per crossing** (a single digest per run) and re-arms when the
condition clears. They run automatically with the nightly `bin/sync.php`, or via
the `alerts` cron job; **Settings → Alerts** shows every channel's status with
"Run now" and "Send test alert". After upgrading, run `php database/migrate.php`
once to add the `alerts_log` table.

- **Email** (PHP `mail()`, no third-party service): set `ALERT_EMAIL_TO`
  (comma-separated). Optionally set `ALERT_EMAIL_FROM` to a real mailbox on your
  domain for best deliverability.
- **Telegram** (free, via the Bot API — recommended for instant push):
  1. In Telegram, message **@BotFather**, send `/newbot`, follow the prompts, and
     copy the **bot token** it gives you.
  2. Start a chat with your new bot and send it any message (e.g. "hi") — a bot
     can't message you until you do.
  3. Get your **chat id**: open
     `https://api.telegram.org/bot<YOUR_TOKEN>/getUpdates` in a browser and copy
     `result[].message.chat.id` (a number; negative for a group). To alert a
     group, add the bot to the group and send a message there first.
  4. In `.env` set `ALERT_TELEGRAM_ENABLED=true`, `ALERT_TELEGRAM_BOT_TOKEN=…`,
     `ALERT_TELEGRAM_CHAT_ID=…`, then use **Settings → Alerts → Send test alert**.

```
# Evaluate alerts every 30 minutes (web endpoint)
curl -s "https://ops.example.com/cron.php?job=alerts&token=YOUR_CRON_SECRET"
```

**Domain expiry:** the Domains module can look up expiry dates automatically via
RDAP (with a WHOIS fallback) — no API key required. RDAP queries the domain's
actual registry directly, so this works for domains registered anywhere, not
just ones bought through the same company as the hosting. Use **Check expiry**
on a domain, **Check all expiry** on the list, or schedule the `domains` cron
job above (e.g. daily). Expiry updates the stored date + status and raises
notifications for domains expiring within 30 days or already expired. Some
ccTLDs (e.g. `.co.za`) do not publish machine-readable expiry; those remain
manual and are clearly reported as such.

The domain registry is local and starts empty — it does not automatically
track every domain hosted on the server. Click **Import from hosting
accounts** on the Domains page to add a registry row (linked to its hosting
account and client) for every WHM account's primary domain that isn't tracked
yet, then immediately check expiry for all of them. Safe to click repeatedly —
it only adds what's missing.

Run synchronisation on demand from **Synchronisation → Synchronise now**, or a
single account from its detail page. Client health scores can be recomputed
from **Client Health → Recompute**.

## 8. Production security checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] HTTPS enforced; `SESSION_SECURE_COOKIE=true`
- [ ] `.env` not web-accessible; permissions locked down
- [ ] `bin/create-admin.php` deleted
- [ ] `WHM_MOCK_MODE=false`
- [ ] `WHM_VERIFY_SSL=true`
- [ ] Strong `CRON_SECRET` set
- [ ] Database user limited to this schema
- [ ] Log rotation configured for `storage/logs`

## 9. Maintenance

- **Clear cache:** delete files under `storage/cache/`.
- **Inspect logs:** `storage/logs/app-YYYY-MM-DD.log` (credentials are
  auto-redacted; never commit logs).
- **Create an administrator:** `php bin/create-admin.php`.
- **Run synchronisation manually:** from **Settings → Synchronisation** (added
  in the sync phase) or the CLI sync command.

## 10. Troubleshooting HTTP 500

A 500 after deploying is almost always environmental. Work through these in
order — the app itself surfaces the first two as a plain message, not a blank
page:

1. **Dependencies not installed.** `vendor/` is intentionally git-ignored, so a
   git-based deploy does **not** include it. Run:
   `composer install --no-dev --optimize-autoloader` from the app root.
2. **`storage/` not writable.** The web-server user needs write access to
   `storage/logs`, `storage/sessions`, `storage/cache` (e.g. `chmod -R 0770 storage`).
3. **PHP version.** Requires PHP **8.2+**. On cPanel set it in *MultiPHP Manager*.
   An older version fails to parse enums/typed code and 500s.
4. **`.htaccess` not applied / mod_rewrite off.** Ensure `AllowOverride All`
   (or the host equivalent) and that `mod_rewrite` is enabled. If the document
   root can't point to `/public`, use the root `.htaccess` fallback (see §6).
5. **Read the real error.** Temporarily set `APP_DEBUG=true` in `.env` to see the
   message on-screen, and check `storage/logs/app-YYYY-MM-DD.log`,
   `storage/logs/fatal-YYYY-MM-DD.log`, and the cPanel *Errors* / Apache error
   log. Set `APP_DEBUG=false` again once resolved.
6. **Database.** If login (a page needing no DB) works but other pages 500, the
   database is misconfigured or migrations haven't run:
   `php database/migrate.php --seed`.

## 11. WHM data availability (reseller read-only token)

Some data may be limited for a reseller/read-only token. The dashboard degrades
gracefully ("Unavailable with current WHM permissions") rather than crashing.
Likely-limited areas: server-wide `get_server_information`/`servicestatus`
(root-only on some servers), per-mailbox data (needs `cpanel-api`), and
`listsuspended` on restricted resellers. The **Settings → WHM → Capabilities**
checker reports exactly what the current token can call.

---

© The Paragon Design — internal use only.
