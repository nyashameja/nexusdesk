# Paragon HostOps — Architecture Overview

This document answers the "First Development Task" review points: the proposed
architecture, database model, routes, phases, and WHM data-availability notes
for a reseller read-only token.

## 1. Application architecture

A lightweight, framework-free MVC-inspired design with explicit layers:

```
HTTP → public/index.php (front controller)
     → bootstrap/app.php (env, config, DI container, session, routes)
     → Core\App (kernel: security headers, exception→error-page)
     → Core\Router (matches route, runs middleware pipeline)
     → Middleware (CSRF → Auth → Permission)
     → Controllers  (thin; no SQL, no cURL)
     → Services      (Auth, AuditLogger, WHM\*)  ← business logic
     → Repositories  (all SQL, PDO prepared statements)
     → Core\Database (PDO) / Whm\WhmApiClient (cURL)
     → Core\View (plain-PHP templates + layout)
```

Key principles enforced:

- **No SQL in views or controllers** — repositories own all queries.
- **No cURL in controllers** — the `WhmApiClient` service owns all WHM calls;
  the browser never talks to WHM.
- **Server-side authorisation on every route** via `perm:<slug>` middleware;
  hidden UI is a convenience, never the security boundary.
- **Secrets only in the environment** — the WHM token never reaches the view,
  JS, API responses, logs (auto-redacted), or the database.
- Dependency injection through a small container; no global mutable state.

### WHM integration design

`WhmApiClient` depends on a `WhmTransportInterface`, which has two
implementations: `CurlTransport` (HTTPS, SSL verify, timeouts, cautious retry)
and `MockTransport` (fixtures, for `WHM_MOCK_MODE`). This makes the client fully
unit-testable and lets development proceed without the live server. Five typed
exceptions (`WhmConnection/Authentication/Permission/Api/InvalidResponse`) each
carry a **safe message** distinct from technical detail. A `CapabilityChecker`
probes which read-only functions the token can call; a `ConnectionTester` runs
the structured "Test WHM Connection" check.

## 2. Database model & relationships

Grouped, fully in `database/schema.sql` (UTC timestamps, `DECIMAL(12,2)` money,
soft-deletes on user-managed tables, indexed FKs):

- **Auth/RBAC:** `users` –< `user_roles` >– `roles` –< `role_permissions` >–
  `permissions`; `login_attempts`, `activity_logs`, `application_settings`,
  `notifications`.
- **CRM:** `clients` –< `client_contacts`/`client_notes`/`client_tags`.
- **WHM cache:** `servers` –< `whm_accounts` –1 `whm_account_usage`,
  `whm_accounts` –< `whm_ssl_certificates`; `whm_packages`,
  `whm_api_capabilities`, `whm_sync_runs` –< `whm_sync_errors`.
- **Domains:** `domains` –< `domain_renewals` (FK to `clients`, `whm_accounts`).
- **Sites/monitoring:** `wordpress_sites`, `uptime_monitors` –< `uptime_checks`.
- **Finance:** `services`, `subscriptions`, `invoices` –< `payments`,
  `financial_entries`.
- **Health:** `health_scores` –< `health_score_factors` (polymorphic subject:
  client or account).

`whm_accounts.client_id` is the manual link between a WHM account and a CRM
client (administrator-confirmed).

## 3. Route structure

Clean URLs via the front controller (`index.php` never appears). Web routes:
`/login`, `/logout`, `/dashboard`, `/accounts`, `/clients`, `/domains`, `/ssl`,
`/email`, `/wordpress`, `/uptime`, `/security`, `/finance`, `/reports`,
`/health`, `/sync` (+`/sync/history`), `/audit-logs`, `/settings/whm`,
`/settings/users`, `/settings/roles`. Internal JSON API (backend-only):
`/api/whm/test-connection`, `/api/whm/capabilities`. Implemented so far:
login/logout, dashboard, accounts (cached list), WHM settings + the two WHM
API endpoints. The remaining routes are wired incrementally per phase; each is
protected by the same middleware pattern.

## 4. Development phases

1. **Foundation** — structure, container, router, auth, RBAC, CSRF, sessions,
   audit logging, schema, admin setup, base layout. ✅
2. **WHM integration** — client, exceptions, connection test, capability
   checker, normalizer, and the read-only sync service (accounts, disk,
   bandwidth, SSL, packages) with run history, advisory locking, CLI runner
   (`bin/sync.php`) and a secret-protected web cron endpoint
   (`public/cron.php`). ✅
3. **Hosting dashboard** — executive dashboard with six Chart.js charts (disk,
   bandwidth, package mix, active/suspended, SSL distribution, growth), the
   attention list, and the full accounts table (search + 7 filters + sortable
   columns + pagination) plus the read-only account-detail screen with a
   "refresh account data" re-sync. ✅
4. **Business modules** — Client CRM (account linking, notes, financial
   roll-up), Domain registry (expiry alerts 90/60/30/14/7), SSL Centre
   (read-only), Email Centre (read-only per-account summary), WordPress
   registry (manual), Finance dashboard + subscriptions (MRR/ARR, revenue by
   category, payment status, renewals), and the transparent Client Health
   Score (weighted, factor-by-factor, "incomplete" when data is sparse). ✅
5. **Monitoring & reporting** — uptime, security centre, notifications,
   reports, CSV export, cron endpoints.
6. **Hardening** — security/permission/error/responsive/index reviews,
   production config, cPanel deployment testing, docs.

## 5. WHM data availability with a reseller read-only token

Expected privileges: `list-accts`, `acct-summary`, `list-pkgs`,
`show-bandwidth`, `ssl-info`, `basic-system-info`, `basic-whm-functions`,
`mysql-info`. Likely **limited or unavailable**:

- `get_server_information` / `servicestatus` — often root-only; a reseller may
  get partial or denied results.
- Per-mailbox email data — requires `cpanel-api`; Version 1 shows only
  account-level counts where available.
- `listsuspended` — may be restricted on some resellers (we also derive
  suspension from `listaccts`).
- WordPress/registrar/malware data — not exposed by WHM to this token; entered
  manually and clearly labelled.

The dashboard never crashes on missing data: unavailable functions render
"Unavailable with current WHM permissions", the safe technical reason is logged,
and the capability checker records per-function status.
