# NexusDesk — REST API Specification (Stage 5)

Defines the public/integration REST API: conventions, authentication, errors, rate limiting,
pagination, and the endpoint catalogue for Tickets, Users, Departments, Customers/Companies,
Knowledge Base, Invoices (Zoho-backed), and Notifications. This is the contract the Stage 15
implementation fulfils; the same controllers/services power the web UI, so business rules never fork.

- **Base URL:** `https://<host>/api/v1`
- **Format:** JSON only (`Content-Type: application/json`, `Accept: application/json`).
- **Style:** resource-oriented, plural nouns, HTTP verbs, stateless (no cookies/CSRF — token auth).
- **Versioning:** URI-versioned (`/api/v1`). Breaking changes ship under `/api/v2`; additive changes
  do not bump the version.

---

## 1. Authentication

Bearer tokens (from `api_tokens`, stored hashed). A user creates tokens in
`/admin/settings/api` (or their profile); the plaintext is shown once.

```
Authorization: Bearer nxd_live_9f2c…               # required on every call
```

- Tokens carry the owner's role/permissions, optionally narrowed by a token `abilities` scope.
- A request is authorised only if **both** the token is valid/unrevoked/unexpired **and** the owner's
  permissions allow the action **and** (for customer tokens) the record belongs to the owner's company.
- `401` = missing/invalid/expired token. `403` = valid token, insufficient permission/ownership.
- Optional 2FA does not affect API tokens (tokens are the second factor equivalent for automation).

**Auth endpoints**

| Method | Path | Purpose | Auth |
|---|---|---|---|
| POST | `/auth/login` | Exchange email+password (+2FA code) for a short-lived token | none + rate-limited |
| POST | `/auth/logout` | Revoke the current token | bearer |
| GET | `/auth/me` | Current user + permissions | bearer |
| POST | `/auth/forgot-password` | Trigger reset email | none + rate-limited |

---

## 2. Conventions

### 2.1 Request/response envelope

Success responses return the resource(s) plus metadata:

```json
{
  "data": { "...": "resource or array" },
  "meta": { "pagination": { "page": 1, "per_page": 25, "total": 132, "total_pages": 6 } }
}
```

### 2.2 Errors

Consistent shape, correct HTTP status, machine-readable `code`, and field-level `errors` for 422:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The given data was invalid.",
    "errors": { "subject": ["Subject is required."], "department_id": ["Unknown department."] }
  }
}
```

| Status | When |
|---|---|
| 200 / 201 / 204 | OK / created / deleted-no-content |
| 400 | Malformed request / bad JSON |
| 401 | Not authenticated |
| 403 | Authenticated but not permitted |
| 404 | Resource not found (or not visible to this token) |
| 409 | Conflict (e.g. merge into self, duplicate) |
| 422 | Validation failed (`errors` map present) |
| 429 | Rate limit exceeded (see headers) |
| 500 | Server error (id logged, not leaked) |

### 2.3 Pagination, filtering, sorting, sparse fields

```
GET /tickets?status=open&priority=high&department_id=3&assigned_agent_id=12
            &q=login&sort=-created_at&page=2&per_page=25&fields=id,reference,subject,status
```

- `page` (1-based), `per_page` (default 25, max 100).
- `sort`: comma list; `-` prefix = descending.
- Filters are whitelisted per endpoint (below); unknown filters → `422`.
- `fields`: sparse fieldset to shrink payloads.
- `include`: related resources (e.g. `?include=messages,company`).

### 2.4 Rate limiting

Token-bucket per token (and per IP for unauthenticated auth calls). Every response carries:

```
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 118
X-RateLimit-Reset: 1752422400        # unix epoch
Retry-After: 42                      # on 429 only
```

Defaults: 120 req/min authenticated; 10 req/min on `/auth/*`; overridable in security settings.

### 2.5 Idempotency & concurrency

- Mutating POSTs accept an optional `Idempotency-Key` header (stored briefly) to make retries safe.
- Responses include `updated_at`; conditional updates may send `If-Unmodified-Since` to avoid
  lost updates (→ `409` on mismatch).

### 2.6 Webhooks (outbound, optional)

Admins register webhook URLs for events (`ticket.created`, `ticket.replied`, `ticket.status_changed`,
`sla.breached`, `invoice.synced`). Payloads are signed (`X-NexusDesk-Signature: sha256=…` HMAC) so
receivers can verify authenticity. Delivery is queued (cron), with retries/backoff.

---

## 3. Endpoint catalogue

Permissions in brackets are the required ability slug(s). Customer tokens are additionally scoped to
their own company's records.

### 3.1 Tickets `[tickets.*]`

| Method | Path | Description |
|---|---|---|
| GET | `/tickets` | List/filter tickets `[tickets.view]` |
| POST | `/tickets` | Create a ticket `[tickets.create]` |
| GET | `/tickets/{id}` | Retrieve a ticket (+`include=messages,company,tags,time`) `[tickets.view]` |
| PATCH | `/tickets/{id}` | Update subject/priority/department/tags `[tickets.*]` |
| DELETE | `/tickets/{id}` | Soft-delete `[tickets.delete]` |
| GET | `/tickets/{id}/messages` | List public replies (+internal if permitted) |
| POST | `/tickets/{id}/messages` | Add reply/internal note `[tickets.reply/tickets.note]` |
| POST | `/tickets/{id}/assign` | `{ "agent_id": 12 }` `[tickets.assign]` |
| POST | `/tickets/{id}/transfer` | `{ "department_id": 4 }` `[tickets.transfer]` |
| POST | `/tickets/{id}/escalate` | `{ "to": 7, "reason": "manual" }` `[tickets.escalate]` |
| POST | `/tickets/{id}/merge` | `{ "into_ticket_id": 1030 }` `[tickets.merge]` |
| POST | `/tickets/{id}/split` | `{ "message_id": 55, "subject": "…" }` `[tickets.merge]` |
| POST | `/tickets/{id}/status` | `{ "status": "resolved" }` `[tickets.reply]` |
| POST | `/tickets/{id}/time` | Log time `{ "minutes": 30, "billable": true }` `[tickets.time]` |
| POST | `/tickets/{id}/rate` | Customer CSAT `{ "rating": 5, "comment": "" }` |
| POST | `/tickets/{id}/attachments` | Multipart upload |
| GET | `/tickets/{id}/attachments/{aid}` | Download (guarded, permission-checked) |

Filters: `status, priority, department_id, assigned_agent_id, company_id, requester_id, tag, q,
created_from, created_to, sla_breached`. Sort: `created_at, updated_at, priority, last_reply_at`.

**Create example**

```
POST /api/v1/tickets
{ "subject":"Login broken","department_id":3,"priority":"high",
  "message":"Session expired on every attempt.","requester_email":"jane@acme.com",
  "tags":["auth"] }
→ 201
{ "data": { "id":1042, "reference":"NEXUS-1042", "status":"new", "priority":"high",
            "department":{"id":3,"name":"Support"}, "created_at":"2026-07-13T10:02:00Z" } }
```

### 3.2 Users `[users.manage]`

| Method | Path | Description |
|---|---|---|
| GET | `/users` | List/filter (`role`, `company_id`, `is_active`, `q`) |
| POST | `/users` | Create user |
| GET | `/users/{id}` | Retrieve |
| PATCH | `/users/{id}` | Update (role, details, active) |
| DELETE | `/users/{id}` | Soft-delete |
| POST | `/users/{id}/permissions` | Set per-user overrides |

### 3.3 Departments `[departments.manage]` (read: any staff)

| Method | Path | Description |
|---|---|---|
| GET | `/departments` | List (public ones available to customers for ticket creation) |
| POST | `/departments` | Create |
| GET | `/departments/{id}` | Retrieve (+ agents, business hours, SLA) |
| PATCH | `/departments/{id}` | Update |
| DELETE | `/departments/{id}` | Delete (guarded by live tickets) |
| GET | `/departments/{id}/agents` | List staff |

### 3.4 Companies / Customers `[companies.manage]` (customer: own only)

| Method | Path | Description |
|---|---|---|
| GET | `/companies` | List/filter (`q`, `account_manager_id`) |
| POST | `/companies` | Create |
| GET | `/companies/{id}` | 360° (+`include=tickets,invoices,projects,domains,ssl`) |
| PATCH | `/companies/{id}` | Update (incl. `zoho_contact_id` mapping) |
| GET | `/companies/{id}/tickets` | Company tickets |
| GET | `/companies/{id}/services` | Domains/hosting/ssl/renewals |

### 3.5 Knowledge Base `[kb.view]` (write: `[kb.manage]`)

| Method | Path | Description |
|---|---|---|
| GET | `/kb/categories` | Category tree |
| GET | `/kb/articles` | List (`category_id`, `status`, `q` full-text) |
| GET | `/kb/articles/{idOrSlug}` | Retrieve (increments views) |
| POST | `/kb/articles` | Create `[kb.manage]` |
| PATCH | `/kb/articles/{id}` | Update `[kb.manage]` |
| DELETE | `/kb/articles/{id}` | Soft-delete `[kb.manage]` |
| POST | `/kb/articles/{id}/feedback` | `{ "helpful": true }` (public) |

### 3.6 Invoices & finance (Zoho-backed, read-only) `[zoho.view]`

Served from the local `zoho_documents` cache; customer tokens see only their company's documents.

| Method | Path | Description |
|---|---|---|
| GET | `/invoices` | List (`status`, `company_id`, `from`, `to`) |
| GET | `/invoices/{id}` | Invoice detail (cached payload) |
| GET | `/invoices/{id}/pdf` | Stream PDF via guarded proxy (never exposes Zoho URL/token) |
| GET | `/quotes` | List quotes/estimates |
| GET | `/statements` | Account statement summary |
| GET | `/finance/summary?company_id=` | Balance, outstanding, counts (dashboard widget) |
| POST | `/integrations/zoho/sync` | Trigger a sync `[zoho.manage]` (queued) |

### 3.7 Notifications `[bearer]` (own only)

| Method | Path | Description |
|---|---|---|
| GET | `/notifications` | List (`unread=true`) |
| GET | `/notifications/unread-count` | Bell badge count |
| POST | `/notifications/{id}/read` | Mark one read |
| POST | `/notifications/read-all` | Mark all read |

### 3.8 Meta / system

| Method | Path | Description |
|---|---|---|
| GET | `/health` | Liveness + component status (public, minimal) |
| GET | `/lookups` | Statuses, priorities, departments (for building forms) |
| GET | `/search?q=` | Global search across permitted entities |

---

## 4. Security summary (API)

- **Transport:** HTTPS only; HSTS. External calls (Zoho) verify TLS against the system CA bundle.
- **AuthZ:** token → permissions → ownership, enforced in `ApiAuthMiddleware` + service-level gates.
- **Input:** every payload validated by a typed `Request`; reject-by-default; prepared statements.
- **Rate limiting:** per-token + per-IP; strict on `/auth/*`.
- **No data leakage:** `404` (not `403`) for records outside a token's scope where disclosure matters;
  server errors return an opaque id, never a stack trace.
- **Auditing:** mutating API calls write to `audit_logs` with the token owner as actor.
- **Secrets:** tokens hashed at rest; Zoho tokens encrypted; nothing sensitive logged.

---

## 5. OpenAPI

A machine-readable `openapi.yaml` (OpenAPI 3.1) mirroring this catalogue is generated during Stage 15
and served at `/api/v1/openapi.yaml`, with a lightweight docs page at `/api/docs`. This document is
the human-facing source of truth until then.

---

*End of Stage 5 — the design phase is complete. Awaiting approval to begin Stage 6 (code): the
project skeleton (kernel, router, container, middleware, base views, installer shell, test/CI bootstrap).*
