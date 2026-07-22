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
- Bespoke, self-hosted CSS/JS front end (Chart.js is added, vendored locally,
  in the hosting-dashboard phase) — nothing loads from a CDN, so a strict
  Content-Security-Policy can be enforced.

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
app sends HSTS and sets `SESSION_SECURE_COOKIE=true` should be configured.

## 7. Cron Jobs

Scheduled synchronisation and uptime checks run via a protected web endpoint or
CLI script (added in the monitoring phase). Secure the web endpoint with
`CRON_SECRET`. Example cPanel cron entries:

```
# Nightly WHM sync (CLI)
0 2 * * * /usr/local/bin/php /home/USER/hostops/database/migrate.php >/dev/null 2>&1

# Uptime checks every 10 minutes (web, secret-protected) — added in Phase 5
*/10 * * * * curl -s "https://ops.example.com/cron/uptime?token=CRON_SECRET" >/dev/null
```

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

## 10. WHM data availability (reseller read-only token)

Some data may be limited for a reseller/read-only token. The dashboard degrades
gracefully ("Unavailable with current WHM permissions") rather than crashing.
Likely-limited areas: server-wide `get_server_information`/`servicestatus`
(root-only on some servers), per-mailbox data (needs `cpanel-api`), and
`listsuspended` on restricted resellers. The **Settings → WHM → Capabilities**
checker reports exactly what the current token can call.

---

© The Paragon Design — internal use only.
