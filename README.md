# NexusDesk

A modern, production-grade **Help Desk & Client Portal** for web agencies, IT companies, MSPs and
digital businesses. Built in vanilla PHP 8.3 (hand-rolled MVC) for standard **cPanel shared hosting**
— no Docker, no Node server, no Laravel/Symfony required.

> NexusDesk is the client portal and support system. **Zoho Books** remains the accounting system —
> invoices, quotes, statements and payments are integrated via its REST API, not re-implemented.

## Status

This project is being built in **approved, reviewable stages**. See the roadmap in
[`docs/architecture/01-SOFTWARE-ARCHITECTURE.md`](docs/architecture/01-SOFTWARE-ARCHITECTURE.md).

| Stage | Deliverable | State |
|---|---|---|
| 1 | Software architecture, folder structure, roadmap | ✅ Delivered |
| 2 | Database schema, ER diagrams, migrations & seeds | ✅ Delivered |
| 3 | Page inventory, navigation & user journeys | ✅ Delivered |
| 4 | Wireframes & design system | ✅ Delivered |
| 5 | REST API specification | ✅ Delivered |
| 6 | Project skeleton — kernel, router, DI container, middleware, views, installer, tests | ✅ Delivered |
| 7 | Auth + RBAC + users + departments + settings/branding + audit + security core | ✅ Delivered |
| 8 | Help-desk core — tickets, replies, notes, status, assign, transfer, time, SLA | ✅ Delivered |
| 9 | Knowledge base — categories, articles, search, feedback, management | ✅ Delivered |
| 10 | Email engine — SMTP, templates, DB job queue, cron worker | ✅ Delivered |
| 11 | Notifications centre + global search | ✅ Delivered |
| 12+ | Zoho Books, reporting/exports, full REST API, AI, backups, hardening | Planned |

### Cron jobs (cPanel)

```
* * * * *   php /path/nexusdesk/cron/process_jobs.php   # queue worker (email, etc.)
*/5 * * * * php /path/nexusdesk/cron/sla_monitor.php    # SLA breach detection
```

## Running locally

```bash
composer install
# Point a MySQL/MariaDB database at the app, then visit /installer.php,
# or manually:  mysql yourdb < database/schema.sql && mysql yourdb < database/seeds/seed.sql
cp .env.example .env          # set DB + mail credentials (the installer writes this for you)
php -S 127.0.0.1:8000 -t public public/index.php   # dev server (Apache in production)
vendor/bin/phpunit            # run the test suite
```

The document root is `public/`. On cPanel, upload the project and point the domain's document
root at `public/` (a root `.htaccess` also guards internals if you cannot).

## Design documentation

- [`docs/architecture/01-SOFTWARE-ARCHITECTURE.md`](docs/architecture/01-SOFTWARE-ARCHITECTURE.md) — architecture, layering, folder structure, security, integrations, roadmap
- [`docs/architecture/02-DATABASE.md`](docs/architecture/02-DATABASE.md) — schema, ER diagrams, indexing, migration/seed plan · SQL in [`database/`](database/)
- [`docs/architecture/03-PAGES-NAVIGATION-JOURNEYS.md`](docs/architecture/03-PAGES-NAVIGATION-JOURNEYS.md) — ~90 pages, navigation, user journeys for all 5 roles
- [`docs/architecture/04-WIREFRAMES-DESIGN-SYSTEM.md`](docs/architecture/04-WIREFRAMES-DESIGN-SYSTEM.md) — design tokens, components, wireframes
- [`docs/architecture/05-API-SPECIFICATION.md`](docs/architecture/05-API-SPECIFICATION.md) — REST API contract

## Tech stack

PHP 8.3 · MySQL/MariaDB · Apache · Bootstrap 5.3 · Vanilla JS + AJAX · Chart.js · PHPMailer ·
Composer · REST API · Light/Dark themes.

*Code stages (skeleton, features, installer) are added as each stage is approved.*
