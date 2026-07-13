# NexusDesk — Software Architecture

**Stage 1 of the delivery roadmap.** This document defines the system architecture, layering,
folder structure, module boundaries, security posture, integration strategy, and the staged
development roadmap. No application code is written until this stage is approved.

- **Project:** NexusDesk — Help Desk & Client Portal
- **Target host:** cPanel shared hosting (Apache + PHP 8.3 + MariaDB/MySQL)
- **Architecture style:** Vanilla PHP, hand-rolled MVC, layered + modular monolith
- **Status:** Draft for approval
- **Version:** 1.0

---

## 1. Architectural Goals & Constraints

### 1.1 Non-negotiable constraints (from the hosting environment)

| Constraint | Consequence for the design |
|---|---|
| Shared cPanel hosting, no shell/root | No long-running processes, no daemons, no queue workers. Background work runs via **cron** (cPanel cron jobs). |
| No Docker, no Node server | Front-end assets are shipped pre-built/static. No build step required at runtime. |
| Apache + `.htaccess` | Routing is funnelled through a single front controller using `mod_rewrite`. |
| PHP 8.3+ | We use typed properties, enums, readonly, first-class callables, constructor promotion, `match`. |
| MySQL/MariaDB | InnoDB, FK constraints, utf8mb4. No DB features exclusive to Postgres. |
| Composer allowed | We rely on a **small, audited** dependency set (see §7). |

### 1.2 Design goals

1. **Production-grade, not prototype** — every layer has error handling, validation, logging.
2. **Modular monolith** — one deployable, but internally split into modules with clear seams so
   CRM, Projects, Assets, Live Chat, etc. can be added later *without touching the core*.
3. **Secure by default** — CSRF, prepared statements, output escaping, RBAC, rate limiting are
   framework-level concerns, not per-page afterthoughts.
4. **Brandable** — "NexusDesk" is a config value. Logo, colours, name, emails are all data.
5. **Testable** — controllers/services depend on abstractions (repositories, interfaces) so logic
   is unit-testable without a live database.
6. **PSR-compliant** — PSR-4 autoloading, PSR-12 code style, PSR-3 logging interface, PSR-7-ish
   request/response value objects (lightweight, not the full package).

### 1.3 Explicit non-goals

- **We do not build an accounting system.** Invoices/quotes/payments are read from **Zoho Books**
  via its REST API and cached locally. NexusDesk is the *portal*; Zoho Books is the *ledger*.
- **We do not embed an AI provider.** We ship **AI service interfaces + a null/stub driver**. A real
  provider (Anthropic, OpenAI, etc.) is wired in later behind the same interface.
- No SPA framework. Progressive enhancement with vanilla JS + AJAX over server-rendered HTML.

---

## 2. High-Level System Architecture

```
                                   ┌───────────────────────────────────────────┐
                                   │                Browser (SSR + AJAX)         │
                                   │   Bootstrap 5.3 · Vanilla JS · Chart.js     │
                                   └───────────────┬─────────────────────────────┘
                                                   │  HTTPS
                          ┌────────────────────────▼────────────────────────┐
                          │              Apache + .htaccess (mod_rewrite)     │
                          │        all requests → /public/index.php           │
                          └────────────────────────┬────────────────────────┘
                                                   │
              ┌────────────────────────────────────▼─────────────────────────────────────┐
              │                          FRONT CONTROLLER (Kernel)                          │
              │  bootstrap → env/config → error handler → session → router → middleware     │
              └───────┬───────────────────────────────────────────────────────┬────────────┘
                      │                                                         │
        ┌─────────────▼─────────────┐                            ┌─────────────▼──────────────┐
        │      Web pipeline          │                            │       API pipeline          │
        │  (session, CSRF, RBAC)     │                            │  (token auth, rate limit)   │
        └─────────────┬─────────────┘                            └─────────────┬──────────────┘
                      │                                                         │
              ┌────────▼───────────────────────────── Controllers ─────────────▼────────┐
              │  Thin. Validate input → call Services → return View / JSON response.       │
              └────────┬──────────────────────────────────────────────────────────────────┘
                      │
              ┌────────▼──────── Services (business logic / use-cases) ──────────────────┐
              │  TicketService, AuthService, SlaService, NotificationService, ...          │
              │  Orchestrate repositories, enforce rules, emit domain events.              │
              └───┬───────────────────────────┬──────────────────────────┬────────────────┘
                  │                           │                          │
        ┌──────────▼─────────┐   ┌────────────▼───────────┐  ┌───────────▼─────────────┐
        │   Repositories     │   │   Integration clients   │  │      Infrastructure      │
        │ (data access, SQL) │   │  Zoho Books, SMTP,      │  │  Mailer, FileStorage,    │
        │  → Models          │   │  AI driver, Webhooks    │  │  Logger, Cache, Events   │
        └──────────┬─────────┘   └────────────┬───────────┘  └───────────┬─────────────┘
                  │                           │                          │
        ┌──────────▼───────────────────────────▼──────────────────────────▼─────────────┐
        │        MySQL / MariaDB (InnoDB, utf8mb4)   ·   /storage (uploads, logs, cache)  │
        └────────────────────────────────────────────────────────────────────────────────┘

        Out-of-band:  cPanel cron  →  /cron/*.php  (SLA checks, email polling, Zoho sync,
                                                     digest emails, cleanup, backups)
```

### 2.1 Request lifecycle (web)

1. Apache rewrites every non-file request to `public/index.php`.
2. **Kernel** boots: loads Composer autoloader, `.env`/config, registers the error & exception
   handler, sets secure session cookie params, starts the session.
3. **Router** matches method + path to a route → `[Controller, action]` + a middleware stack.
4. **Middleware pipeline** runs in order (e.g. `SecurityHeaders → Session → Auth → Role → Csrf →
   RateLimit`). Any middleware may short-circuit with a redirect/error response.
5. **Controller** action runs: builds a validated input object, calls one or more **Services**.
6. **Service** executes the use-case using **Repositories** (data), **Integration clients**
   (Zoho/SMTP/AI), and **Infrastructure** (mailer, storage, events).
7. Controller receives a result and returns either a **rendered View** (HTML) or a **Response**
   value object (redirect/JSON).
8. Kernel emits headers + body. Uncaught errors → logged + friendly error page (prod) or trace (dev).

### 2.2 Request lifecycle (API)

Same kernel, different pipeline: `SecurityHeaders → ApiAuth (Bearer token) → RateLimit → Json`.
Controllers under `App\Controllers\Api\*` return `JsonResponse` DTOs. No sessions, no CSRF (token-based,
stateless), versioned under `/api/v1`.

---

## 3. Layered Design & Responsibilities

We use a strict, one-directional dependency rule: **outer layers depend on inner layers, never the
reverse.** Controllers know Services; Services know Repositories/Integrations via interfaces;
Repositories know the Database. The Database knows nothing about the app.

| Layer | Directory | Responsibility | May depend on | Must NOT |
|---|---|---|---|---|
| **HTTP / Kernel** | `app/Core` | Bootstrap, routing, request/response, middleware dispatch | Config, Container | Contain business rules |
| **Middleware** | `app/Middleware` | Cross-cutting request gates (auth, CSRF, RBAC, rate limit) | Core, Services (read-only checks) | Contain feature logic |
| **Controllers** | `app/Controllers` | Translate HTTP ↔ use-case. Validate, call service, pick view/response | Services, Requests, Views | Talk to the DB directly |
| **Services** | `app/Services` | Business logic / use-cases. Transactions, rules, orchestration | Repositories, Integrations, Infra (all via interfaces) | Emit HTML or read `$_POST` |
| **Repositories** | `app/Repositories` | All SQL. Map rows ↔ Models. One repo per aggregate | Database, Models | Contain business rules |
| **Models / Entities** | `app/Models` | Typed domain objects + enums. Anemic-but-typed | — (pure) | Query the DB |
| **Integrations** | `app/Integrations` | External API clients (Zoho Books, AI, SMTP transport) | HTTP client, Config | Leak vendor types upward |
| **Infrastructure** | `app/Infrastructure` | Mailer, FileStorage, Logger, Cache, EventBus, TokenManager | Config | Contain feature logic |
| **Views** | `resources/views` | Presentation. PHP templates + partials/layouts | View data (arrays/DTOs) | Query, mutate, or decide business rules |
| **Support** | `app/Support` | Helpers, Traits, value objects, validators | — | Hold state |

### 3.1 Why Repositories *and* Models (not Active Record)

Active Record (model-queries-itself) is quick but couples domain objects to the DB and is hard to
test. We separate them: **Models are typed data**, **Repositories own persistence**. This keeps
Services testable with in-memory fakes and lets us swap the Zoho-cached data source cleanly.

### 3.2 Dependency Injection Container

A small PSR-11-style container (`app/Core/Container.php`) wires interfaces → concrete classes from a
bindings map (`config/services.php`). Controllers/services receive dependencies via constructor.
Example bindings:

```
MailerInterface           → PhpMailerMailer
LoggerInterface           → FileLogger            (PSR-3)
AiProviderInterface       → NullAiProvider        (swap → AnthropicProvider later)
TicketRepositoryInterface → MySqlTicketRepository
ZohoBooksClientInterface  → ZohoBooksClient
CacheInterface            → FileCache             (swap → RedisCache if available)
```

This binding map is the single seam where "later" providers get connected — **no core code changes**.

### 3.3 Domain events (decoupling)

A lightweight **EventBus** (synchronous, in-process) lets features react without hard coupling.
Example: `TicketReplied` event → listeners: `SendCustomerEmail`, `RecalculateSla`,
`CreateNotification`, `RunAiSentiment`. Adding a listener never modifies the `TicketService`.
Heavier/slow reactions (email send, AI calls) are queued to a DB `jobs` table and processed by cron,
so the web request stays fast.

---

## 4. Modular Structure (how future modules plug in)

The app is a **modular monolith**. The **Core** modules ship now; **Extension** modules plug in later.
Each module owns its controllers, services, repositories, models, migrations, views, routes, and
menu/permission registrations, and exposes them through a `ModuleProvider`.

```
Core modules (this project):        Extension modules (future, same pattern):
  ├─ Auth & Users & RBAC              ├─ CRM
  ├─ Tickets (Help Desk core)         ├─ Projects
  ├─ Departments & SLA                ├─ Assets
  ├─ Knowledge Base                   ├─ Contracts
  ├─ Client Portal / Workspace        ├─ Inventory
  ├─ Notifications                    ├─ Appointments
  ├─ Reporting                        ├─ Billing (native)
  ├─ Settings & Branding              ├─ Live Chat
  ├─ Zoho Books integration           ├─ WhatsApp / Teams / Slack channels
  ├─ Email (SMTP + inbound)           └─ (any third-party)
  ├─ REST API
  ├─ AI hooks
  └─ Installer
```

**Module contract** (`app/Modules/<Name>/<Name>Module.php`):

```php
interface ModuleInterface {
    public function name(): string;
    public function routes(Router $router): void;          // register its routes
    public function menu(MenuRegistry $menu): void;         // sidebar/nav entries
    public function permissions(PermissionRegistry $p): void;// declare its permissions
    public function events(EventBus $bus): void;             // subscribe listeners
    public function migrations(): array;                     // paths to its SQL migrations
}
```

The Kernel discovers enabled modules from `config/modules.php`, calls each contract method during
boot. A disabled module contributes nothing — no routes, no menu, no tables loaded. This is the
mechanism that satisfies *"future modules plug in without rewriting the application."*

> For the initial build, Help Desk features (Tickets, Departments, KB, etc.) are implemented as
> first-class modules using this exact contract, proving the extension mechanism on day one.

---

## 5. Complete Folder Structure

```
nexusdesk/
├── public/                          # ← Apache document root (only this is web-exposed)
│   ├── index.php                    # front controller (web + API entry)
│   ├── .htaccess                    # rewrite all → index.php; deny dotfiles
│   ├── installer.php                # installer entry (self-disables after install)
│   ├── robots.txt
│   └── assets/
│       ├── css/                     # compiled/curated CSS (app.css, themes/light.css, dark.css)
│       ├── js/                      # vanilla JS modules (app.js, tickets.js, charts.js, ...)
│       ├── vendor/                  # bootstrap 5.3, chart.js (pinned, local — no CDN dependency)
│       ├── img/                     # default logo, illustrations, favicons
│       └── uploads/  → symlink or guarded dir; real files live in /storage (see note)
│
├── app/
│   ├── Core/                        # framework kernel (no business logic)
│   │   ├── Kernel.php  Router.php  Route.php  Container.php
│   │   ├── Request.php  Response.php  JsonResponse.php  RedirectResponse.php
│   │   ├── Middleware/Pipeline.php   View.php  ErrorHandler.php  Env.php
│   ├── Middleware/                  # AuthMiddleware, RoleMiddleware, CsrfMiddleware,
│   │   │                           #   RateLimitMiddleware, ApiAuthMiddleware, SecurityHeaders
│   ├── Controllers/
│   │   ├── Web/                     # server-rendered controllers
│   │   └── Api/V1/                  # JSON API controllers
│   ├── Services/                    # TicketService, AuthService, SlaService, ...
│   ├── Repositories/                # interfaces/ + MySql implementations
│   ├── Models/                      # entities + Enums/ (TicketStatus, Priority, Role, ...)
│   ├── Requests/                    # input validation objects (CreateTicketRequest, ...)
│   ├── Integrations/
│   │   ├── Zoho/                    # ZohoBooksClient, OAuth token store, DTOs, mappers
│   │   ├── Ai/                      # AiProviderInterface + NullAiProvider + tasks
│   │   └── Mail/                    # inbound email parser (IMAP/pipe), transports
│   ├── Infrastructure/             # Mailer, FileStorage, FileLogger, FileCache, EventBus,
│   │   │                           #   TokenManager, PasswordHasher, JobQueue
│   ├── Modules/                     # module providers (Tickets/, Kb/, Portal/, ...)
│   ├── Support/                     # Helpers, Traits/, Validator, Str, Arr, Money, DateTime
│   └── Events/                      # event classes + Listeners/
│
├── config/
│   ├── app.php  database.php  mail.php  services.php  modules.php  security.php
│   ├── zoho.php  ai.php  cache.php  logging.php
│   └── routes/  (web.php, api.php)
│
├── resources/
│   ├── views/                       # layouts/, partials/, auth/, tickets/, portal/,
│   │   │                           #   admin/, kb/, reports/, emails/, errors/
│   └── lang/                        # en.php, ... (i18n dictionaries)
│
├── database/
│   ├── schema.sql                   # full canonical schema
│   ├── migrations/                  # NNNN_description.sql (ordered, idempotent-ish)
│   ├── seeds/                       # roles, departments, statuses, demo data
│   └── er-diagram.md / .svg         # ER diagram (Stage 2 deliverable)
│
├── storage/                         # ← OUTSIDE public root; chmod-guarded
│   ├── uploads/tickets/  uploads/kb/  uploads/avatars/
│   ├── logs/    cache/    backups/    sessions/    zoho/  (token + cached statements)
│   └── .htaccess                    # "Deny from all" defence-in-depth
│
├── cron/                            # invoked by cPanel cron (php /path/cron/xxx.php)
│   ├── sla_monitor.php  email_fetch.php  zoho_sync.php  notifications_digest.php
│   ├── process_jobs.php  cleanup.php   backup.php
│
├── tests/                           # Unit/ + Feature/ (PHPUnit)
├── docs/                            # architecture, schema, API spec, roadmap, wireframes
├── vendor/                          # composer (phpmailer, phpdotenv, phpunit, ...)
├── composer.json  composer.lock
├── .env.example                     # copied to .env by installer (never committed)
├── .gitignore
└── README.md
```

> **Upload/storage note for cPanel:** the document root is `public/`. Everything sensitive
> (`app/`, `config/`, `storage/`, `.env`) lives **above** the web root. Where a host forces the
> document root to be the project root, the shipped `.htaccess` hard-denies access to every
> directory except `public/` and its assets. Uploaded files are served through a PHP controller that
> checks permissions — never linked directly — so a customer can't read another client's attachment
> by guessing a URL.

---

## 6. Security Architecture

Security is enforced at the framework level so individual pages inherit it by default.

| Threat | Control | Where |
|---|---|---|
| SQL injection | **Prepared statements only** (PDO, bound params). Repositories are the *only* place SQL exists; a lint check forbids string-built queries. | Repositories |
| XSS | Output escaping by default: `View::e()` (htmlspecialchars) on all echoed data; a strict CSP header; JSON responses set correct content-type. | View / SecurityHeaders |
| CSRF | Per-session token, injected into every form + AJAX header; verified by `CsrfMiddleware` on all state-changing web requests. API is exempt (token auth instead). | Middleware |
| Broken auth | `password_hash()` (bcrypt/argon2id), constant-time verify, login throttling, **2FA-ready** (TOTP schema in place), secure session cookies (`HttpOnly`, `Secure`, `SameSite=Lax`), session fixation regeneration on login, idle + absolute timeout. | AuthService / Session |
| Broken access control | **RBAC**: roles → permissions; `RoleMiddleware` + `Gate::allows()` checks in services. Tenant/ownership checks on every customer-scoped record. | Middleware / Services |
| Rate/brute force | `RateLimitMiddleware` (DB or file token-bucket) on login, password reset, API, ticket creation. | Middleware |
| Insecure uploads | Whitelist MIME + extension, size caps, randomised stored names, stored outside web root, served via guarded controller, image re-encoding for avatars, `X-Content-Type-Options: nosniff`. | FileStorage / UploadService |
| Sensitive data at rest | `.env` for secrets (SMTP, Zoho, AI keys), never in DB/logs; Zoho OAuth refresh tokens stored encrypted; audit log of privileged actions. | Config / Infra |
| Transport | HSTS + secure-cookie flags; installer nudges HTTPS; all external API calls over TLS with cert verification (uses the environment CA bundle). | SecurityHeaders / clients |
| Headers | CSP, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, `X-Content-Type-Options`. | SecurityHeaders middleware |
| Input | Central `Validator` + typed `Request` objects; reject-by-default, validate on the server regardless of client-side checks. | Requests / Validator |
| Auditability | `audit_logs` table: actor, action, entity, before/after, IP, UA, timestamp. | AuditService |

**Roles & permissions model:** `roles` × `permissions` many-to-many (`role_permissions`), users
carry a role plus optional per-user permission overrides. Permissions are declared *per module* so
extension modules add their own without editing core (e.g. `crm.leads.view`).

Roles: **Administrator, Manager, Support Agent, Customer, Guest** — mapped to permission sets in seed
data, fully editable in the admin UI afterwards.

---

## 7. Third-Party Dependencies (Composer)

Kept deliberately small and audited — every dependency is a shared-hosting-friendly, pure-PHP library.

| Package | Purpose | Notes |
|---|---|---|
| `phpmailer/phpmailer` | SMTP sending | Required by spec. |
| `vlucas/phpdotenv` | `.env` loading | Secrets out of code. |
| `phpunit/phpunit` (dev) | Testing | Dev-only. |
| `firebase/php-jwt` *(candidate)* | API tokens / 2FA transport | Or hand-rolled HMAC tokens to avoid the dep — decided in Stage 8 (API). |
| `dompdf/dompdf` *(candidate)* | PDF export (reports, printable ticket) | Pure PHP, works on shared hosting. Alternative: server-side print CSS. |
| `phpoffice/phpspreadsheet` *(candidate)* | Excel export | Heavier; loaded only for the reporting module. CSV is dependency-free and always available. |

Front-end libraries (**Bootstrap 5.3, Chart.js**) are vendored locally under `public/assets/vendor`
(no runtime CDN dependency) so the portal works even behind restrictive networks and offline demos.
HTTP calls to Zoho/AI use PHP's cURL (present on cPanel) wrapped in a thin `HttpClient`.

> Candidates marked *(candidate)* are confirmed or dropped at the stage that needs them, to avoid
> committing to a dependency before we know the exact requirement.

---

## 8. Integration Architecture

### 8.1 Zoho Books (accounting is *external*)

- **Auth:** OAuth 2.0. The installer/admin settings capture Client ID/Secret + a one-time
  authorization; we store the **refresh token** (encrypted) and mint short-lived access tokens on
  demand, caching them until expiry in `storage/zoho`.
- **Data flow:** `ZohoBooksClient` (implements `ZohoBooksClientInterface`) fetches invoices, quotes,
  credit notes, payments, statements, recurring invoices, and PDFs. A **cron sync** (`zoho_sync.php`)
  pulls per-customer financial summaries into a **local cache table** so portal dashboards render
  instantly and survive Zoho rate limits / outages. PDFs are streamed on-demand through a guarded
  controller (never exposing Zoho URLs/tokens to the browser).
- **Mapping:** a NexusDesk customer/company links to a Zoho `contact_id`. Mapping table +
  admin "link to Zoho" action. If unlinked, the finance widgets degrade gracefully ("not connected").
- **Isolation:** all Zoho types stay inside `App\Integrations\Zoho`; the rest of the app sees plain
  NexusDesk DTOs (`InvoiceSummary`, `Statement`, …). Swapping Zoho for another ledger later means one
  new client class.

### 8.2 Email (SMTP out + inbound)

- **Outbound:** `MailerInterface` → `PhpMailerMailer`, driven by DB/`.env` SMTP settings, sending
  templated emails (`resources/views/emails`). Sends are **queued** to the `jobs` table and flushed
  by cron so a slow SMTP server never blocks a web request.
- **Inbound (auto ticket creation):** two supported modes for cPanel:
  1. **Pipe-to-program** (cPanel "Forwarders → Pipe"): mail piped to `cron/email_fetch.php`.
  2. **IMAP polling** via cron for hosts without piping.
  A parser extracts sender, subject, body, attachments, and threading headers (`In-Reply-To` /
  a `[#NEXUS-1234]` token) to append to the right ticket or open a new one.

### 8.3 AI hooks (provider connected later)

- `AiProviderInterface` with task methods: `reply()`, `summarize()`, `categorize()`, `sentiment()`,
  `translate()`, `rewrite()`, `suggestKbArticles()`.
- Ships with **`NullAiProvider`** (deterministic stub: returns safe placeholders, never fails) so the
  UI/flows are fully built and testable now.
- Each capability has a thin **service** (`AiReplyService`, …) that the UI calls; swapping in a real
  provider is a one-line binding change in `config/ai.php` + one new driver class. Calls run through
  the job queue where latency matters.

---

## 9. Front-End Architecture

- **Server-rendered** HTML via PHP view templates with a shared **layout** (top bar, collapsible
  sidebar, notification centre, theme toggle) and **partials** (cards, tables, modals, form fields).
- **Bootstrap 5.3** as the grid/utility base, wrapped in a **NexusDesk design layer** (CSS custom
  properties for brand colours, spacing scale, radius, shadows) — *not* a stock admin template.
  Modern 2026 aesthetic: soft cards, generous spacing, subtle gradients/glass on key surfaces,
  refined typography, micro-interactions.
- **Theming:** light/dark via `data-bs-theme` + CSS variables; brand colours are injected from
  Settings, so re-branding is a settings change, not a code change.
- **JS:** small vanilla ES modules, progressively enhancing forms with AJAX (ticket reply, live
  search, notifications polling, uploads with progress). **Chart.js** for dashboards. No framework,
  no bundler required — files are hand-split and cache-busted by a version query string.
- **Accessibility & responsive:** semantic HTML, ARIA on interactive widgets, keyboard nav, WCAG-AA
  contrast in both themes, mobile-first breakpoints (desktop / tablet / mobile).

---

## 10. Configuration, Environments & Installer

- **Config precedence:** `.env` (secrets, per-install) → `config/*.php` (typed defaults) → DB
  `settings` table (admin-editable runtime settings like branding, timezone, feature flags).
- **Installer wizard** (`public/installer.php`): requirements check (PHP version, extensions: pdo,
  curl, mbstring, openssl, gd, imap; folder writability) → DB connection + schema/seed run → admin
  account → SMTP → company/branding → finish (writes `.env`, then **locks itself** via a
  `storage/installed.lock` file and refuses to run again).
- **Backups:** `cron/backup.php` (and an admin-triggered action) dumps the DB (mysqldump if
  available, else PHP-driven export) + zips `storage/uploads`, retaining N copies in
  `storage/backups`; restore flow in admin with confirmation + safety checks.

---

## 11. Observability & Operations

- **Logging:** PSR-3 `FileLogger` with daily rotation in `storage/logs`; levels honoured; secrets
  scrubbed. Separate channels for `app`, `security`, `mail`, `zoho`, `cron`.
- **Audit trail:** privileged/customer-visible actions recorded in `audit_logs`.
- **Error handling:** global handler → friendly page (prod) / detailed trace (dev, gated by
  `APP_ENV`); fatal shutdown handler catches parse/OOM.
- **Health:** a lightweight `/health` (API) and an admin "System status" panel (DB, mail, Zoho,
  cron last-run, disk, queue depth).
- **Cron registry:** documented cPanel cron lines with recommended cadences (SLA every 5 min, email
  fetch every 5 min, Zoho sync hourly, digest daily, cleanup nightly, backup nightly).

---

## 12. Testing Strategy

- **Unit tests** (PHPUnit): services with faked repositories/integrations — business rules, SLA
  calculations, permission gates, validators.
- **Feature tests:** boot the kernel against a test DB, drive routes end-to-end (ticket create →
  reply → close), assert responses + DB state.
- **Integration clients** tested against recorded fixtures (no live Zoho/AI in CI).
- **Security checks:** CSRF/RBAC/rate-limit covered by feature tests; static checks forbid raw SQL
  concatenation and unescaped output in views.

---

## 13. Development Roadmap (staged delivery)

Each stage is a reviewable deliverable. **I stop after each stage for your approval before starting
the next** — per your instruction. Design stages (2–8) produce documents; build stages (9+) produce
working, tested code committed to the branch.

| Stage | Deliverable | Output type |
|---|---|---|
| **1** | **Software Architecture** *(this document)* + folder structure + roadmap | ✅ Docs |
| 2 | Database schema, ER diagram, constraints, indexes, migrations & seed plan | Docs + SQL |
| 3 | Full page inventory, navigation map, and user journeys (all 5 roles) | Docs |
| 4 | UI wireframes (low-fi) for every key screen + the design system spec | Docs / visual |
| 5 | REST API specification (endpoints, auth, payloads, errors, versioning) | Docs |
| 6 | Project skeleton: kernel, router, container, middleware, base views, installer shell, CI/tests bootstrap | Code |
| 7 | Auth + RBAC + Users + Departments + Settings/Branding + audit + security core | Code |
| 8 | Help Desk core: tickets (create/reply/assign/transfer/escalate/merge/split, notes, time, statuses, priorities, SLA), attachments, ratings | Code |
| 9 | Customer Portal & Client Workspace (dashboards, tickets, documents) | Code |
| 10 | Knowledge Base (categories, articles, search, ratings, media) | Code |
| 11 | Email engine (SMTP out, templates, inbound → auto ticket) | Code |
| 12 | Notifications (centre, unread, toasts, email) + global search | Code |
| 13 | Zoho Books integration (invoices, quotes, statements, PDFs, sync) | Code |
| 14 | Reporting & dashboards (Chart.js, exports PDF/Excel/CSV) | Code |
| 15 | REST API implementation + token management | Code |
| 16 | AI hooks (interfaces + null driver + UI touchpoints) | Code |
| 17 | Installer wizard, backups/restore, hardening pass, docs & deployment guide | Code |

> Stages are sized to be independently shippable. If you'd prefer to **batch the remaining design
> stages (2–5)** into one pass to move faster, say so and I'll deliver them together — otherwise I
> proceed one stage at a time.

---

## 14. Key Architectural Decisions (summary)

1. **Hand-rolled MVC over a micro-framework** — full control, zero server requirements, easy cPanel
   deployment, no framework lock-in. Cost: we build the kernel (Stage 6), which is small and covered
   by tests.
2. **Repository + Service layering (not Active Record)** — testable business logic and a clean seam
   for the Zoho-backed finance data.
3. **Modular monolith with a Module contract** — satisfies "future modules plug in without a rewrite"
   while shipping a single, simple deployable suited to shared hosting.
4. **Cron-driven background work + a DB job queue** — the only viable async model on cPanel; keeps web
   requests fast (email, AI, Zoho sync run out-of-band).
5. **Everything sensitive above the web root + guarded file serving** — the single biggest security
   win on shared hosting.
6. **Interfaces at every external boundary (Mail, AI, Zoho, Cache, Logger)** — providers swap via the
   container binding map with no core changes; this is where "connect the real AI later" happens.

---

*End of Stage 1. Awaiting approval to proceed to Stage 2 — Database schema & ER diagram.*
