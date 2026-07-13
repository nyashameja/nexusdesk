# NexusDesk — Deployment Guide (cPanel)

This guide covers installing NexusDesk on standard cPanel shared hosting (Apache + PHP 8.3+ +
MySQL/MariaDB). No shell, Docker, or Node server is required.

---

## 1. Requirements

- PHP **8.3+** with extensions: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `json`, `gd`, `zip`
- MySQL 8.0 / MariaDB 10.6+
- Apache with `mod_rewrite` and `.htaccess` overrides enabled
- Composer (run locally if the host lacks it, then upload `vendor/`)

## 2. Upload

1. Upload the project outside or inside `public_html`. **Point the domain's document root at
   `public/`** (cPanel → Domains → set document root). This keeps `app/`, `config/`, `storage/`,
   and `.env` out of the web root.
2. If you cannot change the document root, upload the whole project into `public_html`; the shipped
   root `.htaccess` forwards requests into `public/` and denies access to internals.
3. Ensure `storage/` and its subdirectories are writable (0755/0775).

## 3. Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

If the host has no Composer, run this locally and upload the resulting `vendor/` directory.

## 4. Install

Visit `https://your-domain/installer.php` and complete the wizard:

1. **Requirements** check (all green).
2. **Database** connection (create the DB + user in cPanel → MySQL Databases first).
3. The installer runs `database/schema.sql` + `database/seeds/seed.sql`.
4. **Administrator** account, **SMTP**, **company/branding**.
5. Finish — the installer writes `.env`, drops `storage/installed.lock`, and refuses to run again.

Delete `public/installer.php` after a successful install for extra safety.

## 5. Cron jobs

Add these in cPanel → Cron Jobs (adjust the PHP path and project path):

```
* * * * *   php /home/USER/nexusdesk/cron/process_jobs.php   # email/AI/job queue (every minute)
*/5 * * * * php /home/USER/nexusdesk/cron/sla_monitor.php    # SLA breach detection (5 min)
0 * * * *   php /home/USER/nexusdesk/cron/zoho_sync.php      # Zoho Books cache sync (hourly)
30 2 * * *  php /home/USER/nexusdesk/cron/backup.php         # nightly DB backup
```

## 6. Integrations

- **SMTP** — configure in `.env` (`MAIL_*`); test via Admin → Settings → Email.
- **Zoho Books** — set `ZOHO_CLIENT_ID` / `ZOHO_CLIENT_SECRET` / `ZOHO_REGION` in `.env`, then
  connect via Admin → Settings → Zoho Books (OAuth). Set the redirect URI in the Zoho developer
  console to `https://your-domain/admin/settings/zoho/callback`.
- **AI** — set `AI_PROVIDER` + `AI_API_KEY` in `.env` and select the provider in Admin → Settings →
  AI. (A stub responds until a real driver is wired — see `config/ai.php`.)
- **Inbound email → tickets** — pipe a cPanel forwarder to `cron/email_fetch.php` (planned) or poll
  via IMAP.

## 7. HTTPS

Enable AutoSSL / Let's Encrypt in cPanel. The app sends HSTS and secure-cookie flags over HTTPS and
sets a strict Content-Security-Policy.

## 8. Backups & restore

Backups (gzipped SQL dumps) are created from Admin → Backups or the nightly cron, kept as the 10 most
recent under `storage/backups`. To restore: download a backup and import it via cPanel → phpMyAdmin,
or `gunzip -c backup-*.sql.gz | mysql your_db`.

## 9. Upgrades

Upload the new code, run `composer install --no-dev`, then apply any new
`database/migrations/*.sql` (the migration runner records applied files in the `migrations` table).
Always take a backup first.
