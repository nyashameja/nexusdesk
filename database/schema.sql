-- =====================================================================
-- Paragon HostOps — Database schema (MySQL 8 / MariaDB 10.4+)
-- ---------------------------------------------------------------------
-- Conventions:
--   * InnoDB + utf8mb4.
--   * All timestamps stored in UTC (application converts for display).
--   * Monetary values use DECIMAL(12,2) — never FLOAT.
--   * Soft-delete via nullable deleted_at where records are user-managed.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- Authentication & RBAC ----------
CREATE TABLE IF NOT EXISTS roles (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(64)  NOT NULL,
    name        VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    created_at  TIMESTAMP    NULL DEFAULT NULL,
    updated_at  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(96)  NOT NULL,
    name        VARCHAR(150) NOT NULL,
    `group`     VARCHAR(64)  NULL,
    created_at  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_rp_permission (permission_id),
    CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles (id)       ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(150) NOT NULL,
    email          VARCHAR(190) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    twofa_secret   VARCHAR(255) NULL,          -- reserved for future 2FA
    twofa_enabled  TINYINT(1)   NOT NULL DEFAULT 0,
    last_login_at  TIMESTAMP    NULL DEFAULT NULL,
    last_login_ip  VARCHAR(45)  NULL,
    locked_until   TIMESTAMP    NULL DEFAULT NULL,
    created_at     TIMESTAMP    NULL DEFAULT NULL,
    updated_at     TIMESTAMP    NULL DEFAULT NULL,
    deleted_at     TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    KEY idx_ur_role (role_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Clients (CRM) ----------
CREATE TABLE IF NOT EXISTS clients (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_type    ENUM('individual','company') NOT NULL DEFAULT 'individual',
    company_name   VARCHAR(190) NULL,
    first_name     VARCHAR(120) NULL,
    last_name      VARCHAR(120) NULL,
    primary_email  VARCHAR(190) NULL,
    secondary_email VARCHAR(190) NULL,
    phone          VARCHAR(40)  NULL,
    whatsapp       VARCHAR(40)  NULL,
    country        VARCHAR(80)  NULL,
    province       VARCHAR(120) NULL,
    city           VARCHAR(120) NULL,
    billing_address VARCHAR(500) NULL,
    tax_number     VARCHAR(80)  NULL,
    status         ENUM('active','inactive','prospect','archived') NOT NULL DEFAULT 'active',
    health_score   TINYINT UNSIGNED NULL,
    notes          TEXT         NULL,
    created_at     TIMESTAMP    NULL DEFAULT NULL,
    updated_at     TIMESTAMP    NULL DEFAULT NULL,
    deleted_at     TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_clients_email (primary_email),
    KEY idx_clients_status (status),
    KEY idx_clients_company (company_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS client_contacts (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id  INT UNSIGNED NOT NULL,
    name       VARCHAR(150) NOT NULL,
    email      VARCHAR(190) NULL,
    phone      VARCHAR(40)  NULL,
    role       VARCHAR(80)  NULL,
    is_primary TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NULL DEFAULT NULL,
    updated_at TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_cc_client (client_id),
    CONSTRAINT fk_cc_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS client_notes (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NULL,
    body       TEXT         NOT NULL,
    created_at TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_cn_client (client_id),
    CONSTRAINT fk_cn_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_cn_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS client_tags (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id  INT UNSIGNED NOT NULL,
    tag        VARCHAR(64)  NOT NULL,
    created_at TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ct (client_id, tag),
    CONSTRAINT fk_ct_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Servers & WHM cache ----------
CREATE TABLE IF NOT EXISTS servers (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(150) NOT NULL,
    hostname     VARCHAR(190) NULL,
    ip_address   VARCHAR(45)  NULL,
    whm_version  VARCHAR(60)  NULL,
    os           VARCHAR(120) NULL,
    notes        VARCHAR(500) NULL,
    created_at   TIMESTAMP    NULL DEFAULT NULL,
    updated_at   TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_servers_hostname (hostname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whm_packages (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    server_id     INT UNSIGNED NULL,
    name          VARCHAR(190) NOT NULL,
    disk_quota_mb BIGINT       NULL,
    bandwidth_mb  BIGINT       NULL,
    max_addon     INT          NULL,
    max_sub       INT          NULL,
    max_email     INT          NULL,
    raw_meta      JSON         NULL,
    created_at    TIMESTAMP    NULL DEFAULT NULL,
    updated_at    TIMESTAMP    NULL DEFAULT NULL,
    deleted_at    TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_pkg_server (server_id),
    KEY idx_pkg_name (name),
    CONSTRAINT fk_pkg_server FOREIGN KEY (server_id) REFERENCES servers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whm_accounts (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    server_id      INT UNSIGNED NULL,
    client_id      INT UNSIGNED NULL,
    username       VARCHAR(64)  NOT NULL,
    domain         VARCHAR(190) NOT NULL,
    owner          VARCHAR(64)  NULL,
    email          VARCHAR(190) NULL,
    package        VARCHAR(190) NULL,
    ip_address     VARCHAR(45)  NULL,
    theme          VARCHAR(80)  NULL,
    locale         VARCHAR(40)  NULL,
    suspended      TINYINT(1)   NOT NULL DEFAULT 0,
    suspend_reason VARCHAR(255) NULL,
    ssl_status     VARCHAR(40)  NULL DEFAULT 'unknown',
    whm_created_at DATE         NULL,
    last_synced_at TIMESTAMP    NULL DEFAULT NULL,
    created_at     TIMESTAMP    NULL DEFAULT NULL,
    updated_at     TIMESTAMP    NULL DEFAULT NULL,
    deleted_at     TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_acct_server_user (server_id, username),
    KEY idx_acct_domain (domain),
    KEY idx_acct_username (username),
    KEY idx_acct_email (email),
    KEY idx_acct_client (client_id),
    KEY idx_acct_suspended (suspended),
    CONSTRAINT fk_acct_server FOREIGN KEY (server_id) REFERENCES servers (id) ON DELETE SET NULL,
    CONSTRAINT fk_acct_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whm_account_usage (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id         INT UNSIGNED NOT NULL,
    disk_used_mb       BIGINT       NULL DEFAULT 0,
    disk_limit_mb      BIGINT       NULL DEFAULT 0,
    bandwidth_used_mb  BIGINT       NULL DEFAULT 0,
    bandwidth_limit_mb BIGINT       NULL DEFAULT 0,
    email_accounts     INT          NULL,
    captured_at        TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usage_account (account_id),
    CONSTRAINT fk_usage_account FOREIGN KEY (account_id) REFERENCES whm_accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whm_ssl_certificates (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id     INT UNSIGNED NULL,
    domain         VARCHAR(190) NOT NULL,
    issuer         VARCHAR(190) NULL,
    cert_type      VARCHAR(80)  NULL,
    valid_from     DATE         NULL,
    valid_to       DATE         NULL,
    days_remaining INT          NULL,
    covered_hosts  TEXT         NULL,
    status         ENUM('valid','expiring','expired','invalid','missing','unknown') NOT NULL DEFAULT 'unknown',
    last_checked_at TIMESTAMP   NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_ssl_account (account_id),
    KEY idx_ssl_domain (domain),
    KEY idx_ssl_status (status),
    CONSTRAINT fk_ssl_account FOREIGN KEY (account_id) REFERENCES whm_accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whm_api_capabilities (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    function_name VARCHAR(96) NOT NULL,
    status       ENUM('available','permission_denied','unsupported','server_error','auth_failed','not_tested') NOT NULL DEFAULT 'not_tested',
    message      VARCHAR(255) NULL,
    checked_at   TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cap_function (function_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whm_sync_runs (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sync_type       VARCHAR(40)  NOT NULL,
    status          ENUM('running','completed','failed','partial') NOT NULL DEFAULT 'running',
    records_processed INT        NOT NULL DEFAULT 0,
    records_created INT          NOT NULL DEFAULT 0,
    records_updated INT          NOT NULL DEFAULT 0,
    records_failed  INT          NOT NULL DEFAULT 0,
    message         VARCHAR(500) NULL,
    started_at      TIMESTAMP    NULL DEFAULT NULL,
    finished_at     TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_sync_type (sync_type),
    KEY idx_sync_status (status),
    KEY idx_sync_finished (finished_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whm_sync_errors (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sync_run_id INT UNSIGNED NOT NULL,
    context     VARCHAR(190) NULL,
    message     VARCHAR(500) NOT NULL,
    created_at  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_syncerr_run (sync_run_id),
    CONSTRAINT fk_syncerr_run FOREIGN KEY (sync_run_id) REFERENCES whm_sync_runs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Domains ----------
CREATE TABLE IF NOT EXISTS domains (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id      INT UNSIGNED NULL,
    account_id     INT UNSIGNED NULL,
    domain         VARCHAR(190) NOT NULL,
    registrar      VARCHAR(120) NULL,
    registered_at  DATE         NULL,
    expires_at     DATE         NULL,
    auto_renew     TINYINT(1)   NOT NULL DEFAULT 0,
    nameserver1    VARCHAR(190) NULL,
    nameserver2    VARCHAR(190) NULL,
    status         ENUM('active','expiring','expired','transfer_pending','renewal_pending','cancelled','unknown') NOT NULL DEFAULT 'unknown',
    renewal_cost   DECIMAL(12,2) NULL,
    client_price   DECIMAL(12,2) NULL,
    notes          TEXT         NULL,
    created_at     TIMESTAMP    NULL DEFAULT NULL,
    updated_at     TIMESTAMP    NULL DEFAULT NULL,
    deleted_at     TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_domain (domain),
    KEY idx_domain_client (client_id),
    KEY idx_domain_account (account_id),
    KEY idx_domain_expires (expires_at),
    KEY idx_domain_status (status),
    CONSTRAINT fk_domain_client  FOREIGN KEY (client_id)  REFERENCES clients (id)      ON DELETE SET NULL,
    CONSTRAINT fk_domain_account FOREIGN KEY (account_id) REFERENCES whm_accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS domain_renewals (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    domain_id   INT UNSIGNED NOT NULL,
    renewed_on  DATE         NULL,
    new_expiry  DATE         NULL,
    cost        DECIMAL(12,2) NULL,
    notes       VARCHAR(255) NULL,
    created_at  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_dr_domain (domain_id),
    CONSTRAINT fk_dr_domain FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- WordPress ----------
CREATE TABLE IF NOT EXISTS wordpress_sites (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id        INT UNSIGNED NULL,
    account_id       INT UNSIGNED NULL,
    name             VARCHAR(190) NOT NULL,
    url              VARCHAR(255) NULL,
    staging_url      VARCHAR(255) NULL,
    wp_status        ENUM('healthy','updates_required','maintenance_overdue','backup_overdue','security_review','unknown') NOT NULL DEFAULT 'unknown',
    wp_version       VARCHAR(40)  NULL,
    php_version      VARCHAR(20)  NULL,
    last_backup_at   DATE         NULL,
    last_update_at   DATE         NULL,
    maintenance_plan VARCHAR(120) NULL,
    maintenance_fee  DECIMAL(12,2) NULL,
    manual_entry     TINYINT(1)   NOT NULL DEFAULT 1,
    notes            TEXT         NULL,
    created_at       TIMESTAMP    NULL DEFAULT NULL,
    updated_at       TIMESTAMP    NULL DEFAULT NULL,
    deleted_at       TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_wp_client (client_id),
    KEY idx_wp_account (account_id),
    CONSTRAINT fk_wp_client  FOREIGN KEY (client_id)  REFERENCES clients (id)      ON DELETE SET NULL,
    CONSTRAINT fk_wp_account FOREIGN KEY (account_id) REFERENCES whm_accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Uptime ----------
CREATE TABLE IF NOT EXISTS uptime_monitors (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id        INT UNSIGNED NULL,
    account_id       INT UNSIGNED NULL,
    label            VARCHAR(190) NOT NULL,
    url              VARCHAR(255) NOT NULL,
    expected_status  SMALLINT     NOT NULL DEFAULT 200,
    last_status_code SMALLINT     NULL,
    last_response_ms INT          NULL,
    current_status   ENUM('online','slow','offline','ssl_problem','unknown') NOT NULL DEFAULT 'unknown',
    failure_count    INT          NOT NULL DEFAULT 0,
    ssl_expiry       DATE         NULL,
    enabled          TINYINT(1)   NOT NULL DEFAULT 1,
    last_checked_at  TIMESTAMP    NULL DEFAULT NULL,
    last_success_at  TIMESTAMP    NULL DEFAULT NULL,
    created_at       TIMESTAMP    NULL DEFAULT NULL,
    updated_at       TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_um_enabled (enabled),
    KEY idx_um_client (client_id),
    CONSTRAINT fk_um_client  FOREIGN KEY (client_id)  REFERENCES clients (id)      ON DELETE SET NULL,
    CONSTRAINT fk_um_account FOREIGN KEY (account_id) REFERENCES whm_accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS uptime_checks (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    monitor_id    INT UNSIGNED NOT NULL,
    status_code   SMALLINT     NULL,
    response_ms   INT          NULL,
    is_up         TINYINT(1)   NOT NULL DEFAULT 0,
    error         VARCHAR(255) NULL,
    checked_at    TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_uc_monitor (monitor_id),
    KEY idx_uc_checked (checked_at),
    CONSTRAINT fk_uc_monitor FOREIGN KEY (monitor_id) REFERENCES uptime_monitors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Financial ----------
CREATE TABLE IF NOT EXISTS services (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150) NOT NULL,
    category    ENUM('hosting','domain','ssl','maintenance','other') NOT NULL DEFAULT 'hosting',
    unit_price  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    internal_cost DECIMAL(12,2) NULL,
    created_at  TIMESTAMP    NULL DEFAULT NULL,
    updated_at  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscriptions (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id        INT UNSIGNED NOT NULL,
    account_id       INT UNSIGNED NULL,
    service_id       INT UNSIGNED NULL,
    description      VARCHAR(190) NULL,
    price            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    internal_cost    DECIMAL(12,2) NULL,
    billing_cycle    ENUM('monthly','quarterly','biannual','annual','once') NOT NULL DEFAULT 'monthly',
    next_billing_at  DATE         NULL,
    last_payment_at  DATE         NULL,
    payment_status   ENUM('paid','partial','due','overdue','suspended','complimentary','cancelled') NOT NULL DEFAULT 'due',
    outstanding      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at       TIMESTAMP    NULL DEFAULT NULL,
    updated_at       TIMESTAMP    NULL DEFAULT NULL,
    deleted_at       TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_sub_client (client_id),
    KEY idx_sub_status (payment_status),
    KEY idx_sub_next (next_billing_at),
    CONSTRAINT fk_sub_client  FOREIGN KEY (client_id)  REFERENCES clients (id)      ON DELETE CASCADE,
    CONSTRAINT fk_sub_account FOREIGN KEY (account_id) REFERENCES whm_accounts (id) ON DELETE SET NULL,
    CONSTRAINT fk_sub_service FOREIGN KEY (service_id) REFERENCES services (id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoices (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id    INT UNSIGNED NOT NULL,
    reference    VARCHAR(60)  NULL,
    amount       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status       ENUM('draft','sent','paid','partial','overdue','cancelled') NOT NULL DEFAULT 'draft',
    issued_at    DATE         NULL,
    due_at       DATE         NULL,
    created_at   TIMESTAMP    NULL DEFAULT NULL,
    updated_at   TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_inv_client (client_id),
    KEY idx_inv_status (status),
    CONSTRAINT fk_inv_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id  INT UNSIGNED NULL,
    client_id   INT UNSIGNED NOT NULL,
    amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    method      VARCHAR(60)  NULL,
    paid_at     DATE         NULL,
    reference   VARCHAR(120) NULL,
    created_at  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_pay_invoice (invoice_id),
    KEY idx_pay_client (client_id),
    CONSTRAINT fk_pay_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE SET NULL,
    CONSTRAINT fk_pay_client  FOREIGN KEY (client_id)  REFERENCES clients (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_entries (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id   INT UNSIGNED NULL,
    entry_type  ENUM('hosting','domain','ssl','maintenance','other') NOT NULL DEFAULT 'other',
    description VARCHAR(190) NULL,
    amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    internal_cost DECIMAL(12,2) NULL,
    occurred_on DATE         NULL,
    created_at  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_fe_client (client_id),
    KEY idx_fe_type (entry_type),
    CONSTRAINT fk_fe_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Health scores ----------
CREATE TABLE IF NOT EXISTS health_scores (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_type ENUM('client','account') NOT NULL,
    subject_id   INT UNSIGNED NOT NULL,
    score        TINYINT UNSIGNED NULL,
    band         ENUM('excellent','good','attention','risk','critical','incomplete') NOT NULL DEFAULT 'incomplete',
    computed_at  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hs_subject (subject_type, subject_id),
    KEY idx_hs_band (band)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS health_score_factors (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    health_score_id INT UNSIGNED NOT NULL,
    factor         VARCHAR(120) NOT NULL,
    impact         SMALLINT     NOT NULL DEFAULT 0,
    detail         VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_hsf_score (health_score_id),
    CONSTRAINT fk_hsf_score FOREIGN KEY (health_score_id) REFERENCES health_scores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Platform: audit, auth, settings, notifications ----------
CREATE TABLE IF NOT EXISTS activity_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(80)  NOT NULL,
    entity_type VARCHAR(80)  NULL,
    entity_id   INT UNSIGNED NULL,
    description VARCHAR(500) NULL,
    ip_address  VARCHAR(45)  NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_al_user (user_id),
    KEY idx_al_action (action),
    KEY idx_al_created (created_at),
    CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email       VARCHAR(190) NULL,
    ip_address  VARCHAR(45)  NULL,
    successful  TINYINT(1)   NOT NULL DEFAULT 0,
    user_agent  VARCHAR(255) NULL,
    created_at  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_la_email (email),
    KEY idx_la_ip (ip_address),
    KEY idx_la_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS application_settings (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`      VARCHAR(120) NOT NULL,
    value      TEXT         NULL,
    created_at TIMESTAMP    NULL DEFAULT NULL,
    updated_at TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    type        VARCHAR(60)  NOT NULL,
    title       VARCHAR(190) NOT NULL,
    body        VARCHAR(500) NULL,
    severity    ENUM('info','warning','danger','success') NOT NULL DEFAULT 'info',
    read_at     TIMESTAMP    NULL DEFAULT NULL,
    created_at  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_notif_user (user_id),
    KEY idx_notif_read (read_at),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Alerting (threshold de-duplication) ----------
CREATE TABLE IF NOT EXISTS alerts_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    alert_key  VARCHAR(190) NOT NULL,
    category   VARCHAR(40)  NOT NULL,
    message    VARCHAR(500) NULL,
    sent_at    TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alert_key (alert_key),
    KEY idx_alert_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
