# NexusDesk — Security Posture & Hardening

A summary of the security controls implemented across the application, and the operational hardening
checklist for a production install.

## Controls implemented

| Area | Control |
|---|---|
| SQL injection | All queries use PDO prepared statements with bound parameters; SQL is confined to repositories. No string-built queries. |
| XSS | Output escaped via `View::e()` / `e()` by default; strict `Content-Security-Policy` (self-only scripts, no external hosts); JSON responses set correct content-type. |
| CSRF | Per-session token verified by `VerifyCsrfMiddleware` on all state-changing web requests; injected into every form and AJAX header. API is token-based (CSRF-exempt). |
| Authentication | `password_hash` (bcrypt, cost 12) with constant-time verify + rehash-on-login; secure session cookies (`HttpOnly`, `Secure`, `SameSite=Lax`); session-fixation regeneration on login; idle + absolute timeouts; 2FA-ready schema. |
| Brute force | Login throttling (5 failures / 15 min per email+IP); `login_attempts` audit; token-bucket `RateLimiter` on the API. |
| Access control | RBAC (roles × permissions + per-user overrides); role-guard middleware per area; ownership checks on every customer-scoped record; API scopes customers to their own company. |
| Secrets | `.env` for credentials; Zoho OAuth tokens encrypted at rest (AES-256-GCM via `APP_KEY`); API tokens stored as SHA-256 hashes; secrets scrubbed from logs. |
| Rate limiting | `ApiRateLimitMiddleware` (per-token/IP) emits `X-RateLimit-*` and `429` + `Retry-After`. |
| Headers | CSP, HSTS (HTTPS), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`. |
| File exposure | Sensitive dirs live above the web root; `storage/.htaccess` denies all; root `.htaccess` blocks `app/config/database/storage/...`; `.env` denied. |
| Data integrity | FK constraints, soft-deletes on user-facing aggregates, audit log of privileged actions (`audit_logs`). |
| Input validation | Central `Validator` + typed request access; reject-by-default; server-side validation regardless of client checks. |
| Transport | TLS verification against the system CA on all outbound API calls (`HttpClient`). |

## Operational hardening checklist

- [ ] Serve only over HTTPS (AutoSSL/Let's Encrypt); confirm HSTS is present.
- [ ] Confirm the document root points at `public/`; verify `/.env`, `/app/`, `/storage/` are not web-accessible.
- [ ] Delete `public/installer.php` after installation; confirm `storage/installed.lock` exists.
- [ ] Ensure `APP_ENV=production` and `APP_DEBUG=false` in `.env`.
- [ ] Set a strong, unique `APP_KEY` (the installer generates one) — required for token encryption.
- [ ] Restrict the database user to the application schema only.
- [ ] Configure SMTP with a dedicated account; verify the test email.
- [ ] Set file permissions: code read-only where possible, `storage/` writable, `.env` `600`.
- [ ] Schedule the nightly backup cron and verify a backup restores.
- [ ] Review admin accounts; enable 2FA enforcement for admins in Security settings.
- [ ] Rotate any API tokens that were shared during setup.

## Reporting

Security issues should be reported privately to the system administrator, not via public tickets.
