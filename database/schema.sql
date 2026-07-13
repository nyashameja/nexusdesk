-- =============================================================================
--  NexusDesk — Canonical Database Schema
--  Engine: InnoDB · Charset: utf8mb4 · Collation: utf8mb4_unicode_ci
--  Target: MySQL 8.0 / MariaDB 10.6+  (PHP 8.3, cPanel)
--
--  Conventions
--    * All tables InnoDB, utf8mb4, with created_at/updated_at where mutable.
--    * Surrogate PK `id` BIGINT UNSIGNED AUTO_INCREMENT on every table.
--    * FKs are explicit; ON DELETE chosen per relationship (CASCADE for owned
--      children, SET NULL for optional references, RESTRICT for lookups).
--    * Soft-delete via `deleted_at` on user-facing aggregates (tickets, users,
--      articles, companies) so history/audit survives.
--    * Fixed-but-extensible sets (statuses, priorities, roles) are LOOKUP
--      TABLES (not ENUM) so admins can extend them in the UI.
--    * Monetary values stored as DECIMAL(15,2) + currency code; never floats.
--    * Timestamps stored UTC; display timezone comes from settings/user prefs.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
--  SECTION 1 — IDENTITY, ACCESS CONTROL & SECURITY
-- =============================================================================

CREATE TABLE roles (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(50)     NOT NULL,                 -- administrator, manager, agent, customer, guest
    name          VARCHAR(100)    NOT NULL,
    description   VARCHAR(255)    NULL,
    is_system     TINYINT(1)      NOT NULL DEFAULT 0,       -- system roles cannot be deleted
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(100)    NOT NULL,                 -- e.g. tickets.view, tickets.assign, crm.leads.view
    name          VARCHAR(150)    NOT NULL,
    module        VARCHAR(50)     NOT NULL,                 -- owning module (tickets, kb, crm, ...)
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_slug (slug),
    KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id       BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles (id)       ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id           BIGINT UNSIGNED NOT NULL,
    company_id        BIGINT UNSIGNED NULL,                 -- FK added after companies exists (Section 2)
    first_name        VARCHAR(80)     NOT NULL,
    last_name         VARCHAR(80)     NOT NULL,
    email             VARCHAR(190)    NOT NULL,
    email_verified_at TIMESTAMP       NULL,
    phone             VARCHAR(40)     NULL,
    password_hash     VARCHAR(255)    NOT NULL,
    avatar_path       VARCHAR(255)    NULL,
    job_title         VARCHAR(120)    NULL,
    timezone          VARCHAR(64)     NULL,                 -- overrides system default
    locale            VARCHAR(10)     NULL,                 -- e.g. en
    theme             VARCHAR(10)     NOT NULL DEFAULT 'system', -- light|dark|system
    two_factor_secret VARCHAR(255)    NULL,                 -- encrypted TOTP secret (2FA-ready)
    two_factor_enabled TINYINT(1)     NOT NULL DEFAULT 0,
    is_active         TINYINT(1)      NOT NULL DEFAULT 1,
    last_login_at     TIMESTAMP       NULL,
    last_login_ip     VARBINARY(16)   NULL,
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at        TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    KEY idx_users_company (company_id),
    KEY idx_users_active (is_active),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-user permission overrides (grant/deny on top of the role)
CREATE TABLE user_permissions (
    user_id       BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    effect        ENUM('grant','deny') NOT NULL DEFAULT 'grant',
    PRIMARY KEY (user_id, permission_id),
    CONSTRAINT fk_up_user       FOREIGN KEY (user_id)       REFERENCES users (id)       ON DELETE CASCADE,
    CONSTRAINT fk_up_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email       VARCHAR(190)    NOT NULL,
    token_hash  CHAR(64)        NOT NULL,                   -- sha256 of the emailed token
    expires_at  TIMESTAMP       NOT NULL,
    used_at     TIMESTAMP       NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pwreset_email (email),
    KEY idx_pwreset_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE remember_tokens (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    selector    CHAR(24)        NOT NULL,                   -- lookup half
    token_hash  CHAR(64)        NOT NULL,                   -- validator half (hashed)
    expires_at  TIMESTAMP       NOT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_remember_selector (selector),
    KEY idx_remember_user (user_id),
    CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login attempts: brute-force throttling + security audit
CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email        VARCHAR(190)    NULL,
    ip_address   VARBINARY(16)   NOT NULL,
    successful   TINYINT(1)      NOT NULL DEFAULT 0,
    user_agent   VARCHAR(255)    NULL,
    created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_email_time (email, created_at),
    KEY idx_login_ip_time (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generic token-bucket store for rate limiting (login, api, ticket create, ...)
CREATE TABLE rate_limits (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket_key  VARCHAR(190)    NOT NULL,                   -- e.g. "login:ip:1.2.3.4"
    hits        INT UNSIGNED    NOT NULL DEFAULT 0,
    reset_at    TIMESTAMP       NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rate_bucket (bucket_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      BIGINT UNSIGNED NULL,                      -- actor (null = system/guest)
    action       VARCHAR(100)    NOT NULL,                  -- e.g. ticket.assigned, user.login
    entity_type  VARCHAR(80)     NULL,                      -- e.g. Ticket, User
    entity_id    BIGINT UNSIGNED NULL,
    old_values   JSON            NULL,
    new_values   JSON            NULL,
    ip_address   VARBINARY(16)   NULL,
    user_agent   VARCHAR(255)    NULL,
    created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_action_time (action, created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API tokens (personal access / integration tokens; hashed at rest)
CREATE TABLE api_tokens (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      BIGINT UNSIGNED NOT NULL,
    name         VARCHAR(100)    NOT NULL,
    token_hash   CHAR(64)        NOT NULL,                  -- sha256 of the secret
    abilities    JSON            NULL,                      -- scoped permissions, null = inherit user
    last_used_at TIMESTAMP       NULL,
    expires_at   TIMESTAMP       NULL,
    revoked_at   TIMESTAMP       NULL,
    created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_token_hash (token_hash),
    KEY idx_api_token_user (user_id),
    CONSTRAINT fk_apitoken_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  SECTION 2 — CLIENTS (COMPANIES & CONTACTS)
-- =============================================================================

CREATE TABLE companies (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                VARCHAR(190)    NOT NULL,
    slug                VARCHAR(190)    NOT NULL,
    account_manager_id  BIGINT UNSIGNED NULL,               -- agent/manager owning the account
    email               VARCHAR(190)    NULL,
    phone               VARCHAR(40)     NULL,
    website             VARCHAR(190)    NULL,
    address_line1       VARCHAR(190)    NULL,
    address_line2       VARCHAR(190)    NULL,
    city                VARCHAR(100)    NULL,
    state               VARCHAR(100)    NULL,
    postal_code         VARCHAR(30)     NULL,
    country             VARCHAR(2)      NULL,                -- ISO-3166 alpha-2
    zoho_contact_id     VARCHAR(64)     NULL,                -- link to Zoho Books contact
    notes               TEXT            NULL,
    is_active           TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_companies_slug (slug),
    KEY idx_companies_manager (account_manager_id),
    KEY idx_companies_zoho (zoho_contact_id),
    CONSTRAINT fk_companies_manager FOREIGN KEY (account_manager_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Now that companies exists, add the users.company_id FK
ALTER TABLE users
    ADD CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL;

-- =============================================================================
--  SECTION 3 — DEPARTMENTS, BUSINESS HOURS & SLA
-- =============================================================================

CREATE TABLE departments (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(120)    NOT NULL,                 -- Sales, Accounts, Support, IT, ...
    slug          VARCHAR(120)    NOT NULL,
    email         VARCHAR(190)    NULL,                     -- inbound/outbound address for the dept
    manager_id    BIGINT UNSIGNED NULL,
    sla_policy_id BIGINT UNSIGNED NULL,                     -- default SLA for this dept
    is_public     TINYINT(1)      NOT NULL DEFAULT 1,       -- selectable by customers when opening a ticket
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order    INT             NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_slug (slug),
    KEY idx_departments_manager (manager_id),
    CONSTRAINT fk_dept_manager FOREIGN KEY (manager_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which agents belong to which departments (many-to-many)
CREATE TABLE department_agents (
    department_id BIGINT UNSIGNED NOT NULL,
    user_id       BIGINT UNSIGNED NOT NULL,
    is_lead       TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (department_id, user_id),
    CONSTRAINT fk_da_dept FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE CASCADE,
    CONSTRAINT fk_da_user FOREIGN KEY (user_id)       REFERENCES users (id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Weekly working hours per department (for SLA business-time calculations)
CREATE TABLE business_hours (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    department_id BIGINT UNSIGNED NOT NULL,
    day_of_week   TINYINT UNSIGNED NOT NULL,                -- 0=Sun ... 6=Sat
    open_time     TIME            NULL,                     -- null = closed that day
    close_time    TIME            NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bh_dept_day (department_id, day_of_week),
    CONSTRAINT fk_bh_dept FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Holidays / non-working days (excluded from SLA business time)
CREATE TABLE business_holidays (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    department_id BIGINT UNSIGNED NULL,                     -- null = global holiday
    holiday_date  DATE            NOT NULL,
    name          VARCHAR(120)    NOT NULL,
    PRIMARY KEY (id),
    KEY idx_holiday_dept_date (department_id, holiday_date),
    CONSTRAINT fk_hol_dept FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sla_policies (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                  VARCHAR(120)    NOT NULL,
    description           VARCHAR(255)    NULL,
    use_business_hours    TINYINT(1)      NOT NULL DEFAULT 1, -- else 24/7 clock
    is_active             TINYINT(1)      NOT NULL DEFAULT 1,
    created_at            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-priority targets within a policy (first response + resolution, in minutes)
CREATE TABLE sla_targets (
    id                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sla_policy_id             BIGINT UNSIGNED NOT NULL,
    priority_id               BIGINT UNSIGNED NOT NULL,
    first_response_minutes    INT UNSIGNED    NOT NULL,
    resolution_minutes        INT UNSIGNED    NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sla_target (sla_policy_id, priority_id),
    CONSTRAINT fk_slat_policy   FOREIGN KEY (sla_policy_id) REFERENCES sla_policies (id) ON DELETE CASCADE,
    CONSTRAINT fk_slat_priority FOREIGN KEY (priority_id)   REFERENCES ticket_priorities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link a dept's default SLA policy (added after sla_policies exists)
ALTER TABLE departments
    ADD CONSTRAINT fk_dept_sla FOREIGN KEY (sla_policy_id) REFERENCES sla_policies (id) ON DELETE SET NULL;

-- =============================================================================
--  SECTION 4 — HELP DESK (TICKETS)
-- =============================================================================

-- Lookup: ticket statuses (seeded with the 8 defaults, editable in admin)
CREATE TABLE ticket_statuses (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(40)     NOT NULL,                 -- new, open, pending_customer, ...
    name          VARCHAR(60)     NOT NULL,
    colour        CHAR(7)         NOT NULL DEFAULT '#6c757d',
    is_open_state TINYINT(1)      NOT NULL DEFAULT 1,       -- counts as "open" for dashboards/SLA
    pauses_sla    TINYINT(1)      NOT NULL DEFAULT 0,       -- e.g. pending customer pauses the clock
    is_resolved   TINYINT(1)      NOT NULL DEFAULT 0,
    sort_order    INT             NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_status_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lookup: priorities (Low..Critical, editable)
CREATE TABLE ticket_priorities (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(40)     NOT NULL,
    name          VARCHAR(60)     NOT NULL,
    colour        CHAR(7)         NOT NULL DEFAULT '#6c757d',
    weight        INT             NOT NULL DEFAULT 0,       -- higher = more urgent (sorting/escalation)
    sort_order    INT             NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_priority_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tickets (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference             VARCHAR(20)     NOT NULL,         -- human ref, e.g. NEXUS-1042
    subject               VARCHAR(255)    NOT NULL,
    department_id         BIGINT UNSIGNED NOT NULL,
    status_id             BIGINT UNSIGNED NOT NULL,
    priority_id           BIGINT UNSIGNED NOT NULL,
    sla_policy_id         BIGINT UNSIGNED NULL,             -- resolved at creation from dept/priority
    requester_id          BIGINT UNSIGNED NULL,             -- the customer user (null if guest email)
    requester_email       VARCHAR(190)    NULL,             -- captured for guest / email-in tickets
    requester_name        VARCHAR(160)    NULL,
    company_id            BIGINT UNSIGNED NULL,
    assigned_agent_id     BIGINT UNSIGNED NULL,
    source                ENUM('portal','email','api','phone','chat','agent') NOT NULL DEFAULT 'portal',
    channel_ref           VARCHAR(190)    NULL,             -- inbound message-id / external ref
    merged_into_id        BIGINT UNSIGNED NULL,             -- set when merged into another ticket
    first_response_at     TIMESTAMP       NULL,
    resolved_at           TIMESTAMP       NULL,
    closed_at             TIMESTAMP       NULL,
    due_first_response_at TIMESTAMP       NULL,             -- SLA deadlines (snapshot)
    due_resolution_at     TIMESTAMP       NULL,
    sla_response_breached  TINYINT(1)     NOT NULL DEFAULT 0,
    sla_resolution_breached TINYINT(1)    NOT NULL DEFAULT 0,
    last_reply_at         TIMESTAMP       NULL,
    last_reply_by         ENUM('customer','agent','system') NULL,
    is_locked             TINYINT(1)      NOT NULL DEFAULT 0,
    created_at            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at            TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tickets_reference (reference),
    KEY idx_tickets_department (department_id),
    KEY idx_tickets_status (status_id),
    KEY idx_tickets_priority (priority_id),
    KEY idx_tickets_requester (requester_id),
    KEY idx_tickets_company (company_id),
    KEY idx_tickets_assigned (assigned_agent_id),
    KEY idx_tickets_merged (merged_into_id),
    KEY idx_tickets_due_res (due_resolution_at),
    KEY idx_tickets_status_dept (status_id, department_id),
    KEY idx_tickets_email (requester_email),
    CONSTRAINT fk_tickets_department FOREIGN KEY (department_id)     REFERENCES departments (id)       ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_status     FOREIGN KEY (status_id)         REFERENCES ticket_statuses (id)   ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_priority   FOREIGN KEY (priority_id)       REFERENCES ticket_priorities (id) ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_sla        FOREIGN KEY (sla_policy_id)     REFERENCES sla_policies (id)      ON DELETE SET NULL,
    CONSTRAINT fk_tickets_requester  FOREIGN KEY (requester_id)      REFERENCES users (id)             ON DELETE SET NULL,
    CONSTRAINT fk_tickets_company    FOREIGN KEY (company_id)        REFERENCES companies (id)         ON DELETE SET NULL,
    CONSTRAINT fk_tickets_agent      FOREIGN KEY (assigned_agent_id) REFERENCES users (id)             ON DELETE SET NULL,
    CONSTRAINT fk_tickets_merged     FOREIGN KEY (merged_into_id)    REFERENCES tickets (id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Replies AND internal notes (distinguished by is_internal); system entries too
CREATE TABLE ticket_messages (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id     BIGINT UNSIGNED NOT NULL,
    user_id       BIGINT UNSIGNED NULL,                     -- author (null = system/guest)
    author_type   ENUM('customer','agent','system') NOT NULL,
    body_html     MEDIUMTEXT      NOT NULL,
    body_text     MEDIUMTEXT      NULL,                     -- plain fallback / search
    is_internal   TINYINT(1)      NOT NULL DEFAULT 0,       -- internal note, hidden from customer
    source        ENUM('portal','email','api','agent','system') NOT NULL DEFAULT 'portal',
    email_message_id VARCHAR(190) NULL,                     -- inbound threading
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tmsg_ticket (ticket_id),
    KEY idx_tmsg_user (user_id),
    KEY idx_tmsg_ticket_internal (ticket_id, is_internal),
    CONSTRAINT fk_tmsg_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_tmsg_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Status change history (audit of workflow transitions)
CREATE TABLE ticket_status_history (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id      BIGINT UNSIGNED NOT NULL,
    from_status_id BIGINT UNSIGNED NULL,
    to_status_id   BIGINT UNSIGNED NOT NULL,
    changed_by     BIGINT UNSIGNED NULL,
    note           VARCHAR(255)    NULL,
    created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tsh_ticket (ticket_id),
    CONSTRAINT fk_tsh_ticket FOREIGN KEY (ticket_id)      REFERENCES tickets (id)         ON DELETE CASCADE,
    CONSTRAINT fk_tsh_to     FOREIGN KEY (to_status_id)   REFERENCES ticket_statuses (id) ON DELETE RESTRICT,
    CONSTRAINT fk_tsh_from   FOREIGN KEY (from_status_id) REFERENCES ticket_statuses (id) ON DELETE SET NULL,
    CONSTRAINT fk_tsh_user   FOREIGN KEY (changed_by)     REFERENCES users (id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Time tracking (agent effort per ticket)
CREATE TABLE ticket_time_entries (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id     BIGINT UNSIGNED NOT NULL,
    user_id       BIGINT UNSIGNED NOT NULL,
    minutes       INT UNSIGNED    NOT NULL,
    is_billable   TINYINT(1)      NOT NULL DEFAULT 0,
    note          VARCHAR(255)    NULL,
    started_at    TIMESTAMP       NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tte_ticket (ticket_id),
    KEY idx_tte_user (user_id),
    CONSTRAINT fk_tte_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_tte_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Escalation events (manual or SLA-triggered by cron)
CREATE TABLE ticket_escalations (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id     BIGINT UNSIGNED NOT NULL,
    reason        ENUM('sla_response','sla_resolution','manual') NOT NULL,
    escalated_to  BIGINT UNSIGNED NULL,                     -- agent/manager
    note          VARCHAR(255)    NULL,
    created_by    BIGINT UNSIGNED NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tesc_ticket (ticket_id),
    CONSTRAINT fk_tesc_ticket FOREIGN KEY (ticket_id)    REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_tesc_to     FOREIGN KEY (escalated_to) REFERENCES users (id)   ON DELETE SET NULL,
    CONSTRAINT fk_tesc_by     FOREIGN KEY (created_by)   REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Watchers / CC (agents or contacts following a ticket)
CREATE TABLE ticket_watchers (
    ticket_id  BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ticket_id, user_id),
    CONSTRAINT fk_tw_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_tw_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
    id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name   VARCHAR(60)     NOT NULL,
    slug   VARCHAR(60)     NOT NULL,
    colour CHAR(7)         NOT NULL DEFAULT '#6c757d',
    PRIMARY KEY (id),
    UNIQUE KEY uq_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_tags (
    ticket_id BIGINT UNSIGNED NOT NULL,
    tag_id    BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (ticket_id, tag_id),
    CONSTRAINT fk_tt_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_tt_tag    FOREIGN KEY (tag_id)    REFERENCES tags (id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer satisfaction rating (one per ticket, post-resolution)
CREATE TABLE ticket_ratings (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id   BIGINT UNSIGNED NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL,                  -- 1..5
    comment     VARCHAR(500)    NULL,
    rated_by    BIGINT UNSIGNED NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rating_ticket (ticket_id),
    CONSTRAINT fk_trate_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_trate_user   FOREIGN KEY (rated_by)  REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reusable canned responses / macros for agents
CREATE TABLE canned_responses (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    department_id BIGINT UNSIGNED NULL,                     -- null = global
    title         VARCHAR(150)    NOT NULL,
    body          MEDIUMTEXT      NOT NULL,
    created_by    BIGINT UNSIGNED NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_canned_dept (department_id),
    CONSTRAINT fk_canned_dept FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE CASCADE,
    CONSTRAINT fk_canned_user FOREIGN KEY (created_by)    REFERENCES users (id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  SECTION 5 — ATTACHMENTS (polymorphic)
-- =============================================================================

CREATE TABLE attachments (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attachable_type VARCHAR(60)    NOT NULL,                -- Ticket, TicketMessage, KbArticle, Document
    attachable_id  BIGINT UNSIGNED NOT NULL,
    uploaded_by    BIGINT UNSIGNED NULL,
    original_name  VARCHAR(255)    NOT NULL,
    stored_name    VARCHAR(255)    NOT NULL,                -- randomised; lives under /storage/uploads
    disk_path      VARCHAR(500)    NOT NULL,
    mime_type      VARCHAR(120)    NOT NULL,
    size_bytes     BIGINT UNSIGNED NOT NULL,
    checksum       CHAR(64)        NULL,                    -- sha256 for dedupe/integrity
    is_inline      TINYINT(1)      NOT NULL DEFAULT 0,
    created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attach_morph (attachable_type, attachable_id),
    CONSTRAINT fk_attach_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  SECTION 6 — KNOWLEDGE BASE
-- =============================================================================

CREATE TABLE kb_categories (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id     BIGINT UNSIGNED NULL,                     -- nested categories
    name          VARCHAR(150)    NOT NULL,
    slug          VARCHAR(160)    NOT NULL,
    description   VARCHAR(500)    NULL,
    icon          VARCHAR(60)     NULL,
    is_public     TINYINT(1)      NOT NULL DEFAULT 1,       -- visible to customers/guests
    sort_order    INT             NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kbcat_slug (slug),
    KEY idx_kbcat_parent (parent_id),
    CONSTRAINT fk_kbcat_parent FOREIGN KEY (parent_id) REFERENCES kb_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kb_articles (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id    BIGINT UNSIGNED NOT NULL,
    author_id      BIGINT UNSIGNED NULL,
    title          VARCHAR(255)    NOT NULL,
    slug           VARCHAR(260)    NOT NULL,
    excerpt        VARCHAR(500)    NULL,
    body_html      MEDIUMTEXT      NOT NULL,
    video_url      VARCHAR(255)    NULL,
    status         ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    is_public      TINYINT(1)      NOT NULL DEFAULT 1,      -- else customer-only
    views_count    INT UNSIGNED    NOT NULL DEFAULT 0,
    helpful_count  INT UNSIGNED    NOT NULL DEFAULT 0,
    unhelpful_count INT UNSIGNED   NOT NULL DEFAULT 0,
    published_at   TIMESTAMP       NULL,
    created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at     TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kbart_slug (slug),
    KEY idx_kbart_category (category_id),
    KEY idx_kbart_status (status),
    FULLTEXT KEY ft_kbart (title, excerpt, body_html),
    CONSTRAINT fk_kbart_category FOREIGN KEY (category_id) REFERENCES kb_categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_kbart_author   FOREIGN KEY (author_id)   REFERENCES users (id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Related articles (self many-to-many)
CREATE TABLE kb_article_related (
    article_id  BIGINT UNSIGNED NOT NULL,
    related_id  BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (article_id, related_id),
    CONSTRAINT fk_kbrel_article FOREIGN KEY (article_id) REFERENCES kb_articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_kbrel_related FOREIGN KEY (related_id) REFERENCES kb_articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kb_article_feedback (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id  BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NULL,
    was_helpful TINYINT(1)      NOT NULL,
    comment     VARCHAR(500)    NULL,
    ip_address  VARBINARY(16)   NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kbfb_article (article_id),
    CONSTRAINT fk_kbfb_article FOREIGN KEY (article_id) REFERENCES kb_articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_kbfb_user    FOREIGN KEY (user_id)    REFERENCES users (id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  SECTION 7 — CLIENT WORKSPACE (projects, domains, hosting, ssl, documents)
--  These are portal-facing records NexusDesk manages (not accounting).
-- =============================================================================

CREATE TABLE projects (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id    BIGINT UNSIGNED NOT NULL,
    name          VARCHAR(190)    NOT NULL,
    description   TEXT            NULL,
    status        ENUM('planning','active','on_hold','completed','cancelled') NOT NULL DEFAULT 'active',
    progress      TINYINT UNSIGNED NOT NULL DEFAULT 0,      -- 0..100
    manager_id    BIGINT UNSIGNED NULL,
    start_date    DATE            NULL,
    due_date      DATE            NULL,
    completed_at  DATE            NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_projects_company (company_id),
    CONSTRAINT fk_projects_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_projects_manager FOREIGN KEY (manager_id) REFERENCES users (id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE domains (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id    BIGINT UNSIGNED NOT NULL,
    domain_name   VARCHAR(253)    NOT NULL,
    registrar     VARCHAR(120)    NULL,
    status        ENUM('active','expiring','expired','pending','transferred') NOT NULL DEFAULT 'active',
    auto_renew    TINYINT(1)      NOT NULL DEFAULT 0,
    registered_at DATE            NULL,
    expires_at    DATE            NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_domains_company (company_id),
    KEY idx_domains_expiry (expires_at),
    CONSTRAINT fk_domains_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hosting_accounts (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id    BIGINT UNSIGNED NOT NULL,
    label         VARCHAR(190)    NOT NULL,
    plan          VARCHAR(120)    NULL,
    server        VARCHAR(190)    NULL,
    primary_domain VARCHAR(253)   NULL,
    status        ENUM('active','suspended','expiring','expired','cancelled') NOT NULL DEFAULT 'active',
    disk_quota_mb INT UNSIGNED    NULL,
    disk_used_mb  INT UNSIGNED    NULL,
    renews_at     DATE            NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hosting_company (company_id),
    KEY idx_hosting_renew (renews_at),
    CONSTRAINT fk_hosting_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ssl_certificates (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id    BIGINT UNSIGNED NOT NULL,
    domain_id     BIGINT UNSIGNED NULL,
    common_name   VARCHAR(253)    NOT NULL,
    issuer        VARCHAR(190)    NULL,
    status        ENUM('valid','expiring','expired','revoked','pending') NOT NULL DEFAULT 'valid',
    issued_at     DATE            NULL,
    expires_at    DATE            NULL,
    auto_renew    TINYINT(1)      NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ssl_company (company_id),
    KEY idx_ssl_expiry (expires_at),
    CONSTRAINT fk_ssl_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_ssl_domain  FOREIGN KEY (domain_id)  REFERENCES domains (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Client documents & downloads (contracts, deliverables, guides)
CREATE TABLE documents (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id    BIGINT UNSIGNED NOT NULL,
    uploaded_by   BIGINT UNSIGNED NULL,
    title         VARCHAR(190)    NOT NULL,
    description   VARCHAR(500)    NULL,
    category      VARCHAR(80)     NULL,                     -- contract, invoice-copy, deliverable, guide
    is_visible    TINYINT(1)      NOT NULL DEFAULT 1,       -- visible to the client in the portal
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_documents_company (company_id),
    CONSTRAINT fk_documents_company FOREIGN KEY (company_id)  REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_user    FOREIGN KEY (uploaded_by) REFERENCES users (id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  SECTION 8 — ZOHO BOOKS INTEGRATION (read-only cache; accounting is external)
-- =============================================================================

-- OAuth connection state for Zoho Books (single row for the org connection)
CREATE TABLE zoho_connections (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id     VARCHAR(64)     NULL,               -- Zoho Books org id
    region              VARCHAR(10)      NOT NULL DEFAULT 'com', -- com, eu, in, au ...
    access_token        TEXT            NULL,               -- encrypted at rest
    refresh_token       TEXT            NULL,               -- encrypted at rest
    token_expires_at    TIMESTAMP       NULL,
    connected_by        BIGINT UNSIGNED NULL,
    last_synced_at      TIMESTAMP       NULL,
    is_active           TINYINT(1)      NOT NULL DEFAULT 0,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_zoho_conn_user FOREIGN KEY (connected_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cached financial documents (invoices, quotes, credit notes, payments, statements)
CREATE TABLE zoho_documents (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id        BIGINT UNSIGNED NULL,                 -- mapped local company
    zoho_contact_id   VARCHAR(64)     NOT NULL,
    doc_type          ENUM('invoice','quote','credit_note','payment','recurring_invoice','statement') NOT NULL,
    zoho_document_id  VARCHAR(64)     NOT NULL,
    number            VARCHAR(64)     NULL,                 -- INV-000123
    status            VARCHAR(40)     NULL,                 -- sent, paid, overdue, draft ...
    currency          CHAR(3)         NULL,
    total             DECIMAL(15,2)   NULL,
    balance           DECIMAL(15,2)   NULL,
    issue_date        DATE            NULL,
    due_date          DATE            NULL,
    payload           JSON            NULL,                 -- full cached response for detail views
    synced_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_zoho_doc (doc_type, zoho_document_id),
    KEY idx_zoho_company (company_id),
    KEY idx_zoho_contact (zoho_contact_id),
    KEY idx_zoho_type_status (doc_type, status),
    CONSTRAINT fk_zoho_doc_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  SECTION 9 — NOTIFICATIONS, EMAIL, JOBS, SETTINGS, AI
-- =============================================================================

CREATE TABLE notifications (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       BIGINT UNSIGNED NOT NULL,                 -- recipient
    type          VARCHAR(80)     NOT NULL,                 -- ticket.reply, sla.breach, invoice.new
    title         VARCHAR(190)    NOT NULL,
    body          VARCHAR(500)    NULL,
    url           VARCHAR(255)    NULL,                      -- deep link
    icon          VARCHAR(40)     NULL,
    data          JSON            NULL,
    read_at       TIMESTAMP       NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_user_read (user_id, read_at),
    KEY idx_notif_created (created_at),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_templates (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(80)     NOT NULL,                 -- ticket_created, ticket_reply, password_reset
    name          VARCHAR(150)    NOT NULL,
    subject       VARCHAR(255)    NOT NULL,
    body_html     MEDIUMTEXT      NOT NULL,
    body_text     MEDIUMTEXT      NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_emailtpl_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inbound email log (raw parse record for auto ticket creation / threading)
CREATE TABLE inbound_emails (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id     VARCHAR(190)    NULL,
    from_email     VARCHAR(190)    NOT NULL,
    to_email       VARCHAR(190)    NULL,
    subject        VARCHAR(255)    NULL,
    ticket_id      BIGINT UNSIGNED NULL,                    -- resolved ticket (new or matched)
    status         ENUM('pending','processed','failed','ignored') NOT NULL DEFAULT 'pending',
    error          VARCHAR(500)    NULL,
    received_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at   TIMESTAMP       NULL,
    PRIMARY KEY (id),
    KEY idx_inbound_status (status),
    KEY idx_inbound_ticket (ticket_id),
    CONSTRAINT fk_inbound_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Async job queue (processed by cron; email send, AI tasks, zoho sync, digests)
CREATE TABLE jobs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    queue         VARCHAR(60)     NOT NULL DEFAULT 'default',
    type          VARCHAR(120)    NOT NULL,                 -- SendEmailJob, AiSummariseJob, ...
    payload       JSON            NOT NULL,
    attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts  TINYINT UNSIGNED NOT NULL DEFAULT 3,
    available_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reserved_at   TIMESTAMP       NULL,
    status        ENUM('pending','reserved','done','failed') NOT NULL DEFAULT 'pending',
    last_error    VARCHAR(1000)   NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_jobs_poll (status, available_at),
    KEY idx_jobs_queue (queue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Runtime settings (admin-editable), grouped; value stored as JSON for typing
CREATE TABLE settings (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_name    VARCHAR(60)     NOT NULL,                 -- general, branding, mail, security, zoho, ai
    key_name      VARCHAR(80)     NOT NULL,
    value         JSON            NULL,
    is_secret     TINYINT(1)      NOT NULL DEFAULT 0,       -- masked in UI / never logged
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (group_name, key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log of AI operations (audit + cost/usage visibility; provider-agnostic)
CREATE TABLE ai_requests (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       BIGINT UNSIGNED NULL,
    task          VARCHAR(40)     NOT NULL,                 -- reply, summary, categorize, sentiment, translate, rewrite, kb_suggest
    provider      VARCHAR(40)     NULL,                     -- null|anthropic|openai (whatever is wired)
    entity_type   VARCHAR(60)     NULL,
    entity_id     BIGINT UNSIGNED NULL,
    tokens_in     INT UNSIGNED    NULL,
    tokens_out    INT UNSIGNED    NULL,
    status        ENUM('ok','error','stubbed') NOT NULL DEFAULT 'stubbed',
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ai_user (user_id),
    KEY idx_ai_task (task),
    CONSTRAINT fk_ai_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Schema/version bookkeeping for the migration runner
CREATE TABLE migrations (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration  VARCHAR(190)    NOT NULL,
    batch      INT             NOT NULL,
    ran_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
--  END OF SCHEMA
-- =============================================================================
