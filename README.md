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
| 6 | Project skeleton (code) | ⏳ Next — awaiting approval |
| 7+ | Auth, tickets, portal, KB, email, Zoho, reports, API, AI, installer | Planned |

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
