-- =============================================================================
--  NexusDesk — Seed Data (defaults required for a working install)
--  Run AFTER schema.sql. Idempotent-ish: uses INSERT ... (installer wraps in txn).
--  NOTE: the demo admin password hash below is a placeholder; the installer
--        replaces it with the admin account created in the wizard.
-- =============================================================================

SET NAMES utf8mb4;

-- ---------- Roles -----------------------------------------------------------
INSERT INTO roles (slug, name, description, is_system) VALUES
  ('administrator', 'Administrator', 'Full system access',                    1),
  ('manager',       'Manager',       'Department oversight, reports, SLA',     1),
  ('agent',         'Support Agent', 'Handles tickets',                        1),
  ('customer',      'Customer',      'Client portal user',                     1),
  ('guest',         'Guest',         'Unauthenticated / KB & ticket tracking', 1);

-- ---------- Permissions (module.action) -------------------------------------
INSERT INTO permissions (slug, name, module) VALUES
  ('tickets.view',        'View tickets',            'tickets'),
  ('tickets.create',      'Create tickets',          'tickets'),
  ('tickets.reply',       'Reply to tickets',        'tickets'),
  ('tickets.assign',      'Assign tickets',          'tickets'),
  ('tickets.transfer',    'Transfer tickets',        'tickets'),
  ('tickets.escalate',    'Escalate tickets',        'tickets'),
  ('tickets.merge',       'Merge/split tickets',     'tickets'),
  ('tickets.note',        'Add internal notes',      'tickets'),
  ('tickets.time',        'Log time',                'tickets'),
  ('tickets.delete',      'Delete tickets',          'tickets'),
  ('departments.manage',  'Manage departments',      'departments'),
  ('kb.view',             'View knowledge base',     'kb'),
  ('kb.manage',           'Manage knowledge base',   'kb'),
  ('reports.view',        'View reports',            'reports'),
  ('users.manage',        'Manage users',            'users'),
  ('companies.manage',    'Manage companies',        'companies'),
  ('settings.manage',     'Manage settings',         'settings'),
  ('branding.manage',     'Manage branding',         'settings'),
  ('api.manage',          'Manage API tokens',       'api'),
  ('zoho.view',           'View Zoho finance data',  'zoho'),
  ('zoho.manage',         'Manage Zoho connection',  'zoho'),
  ('portal.access',       'Access client portal',    'portal'),
  ('ai.use',              'Use AI assistance',       'ai');

-- Administrator gets everything
INSERT INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE slug='administrator'), id FROM permissions;

-- Manager: everything except destructive/system settings
INSERT INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE slug='manager'), id FROM permissions
  WHERE slug IN ('tickets.view','tickets.create','tickets.reply','tickets.assign',
                 'tickets.transfer','tickets.escalate','tickets.merge','tickets.note',
                 'tickets.time','departments.manage','kb.view','kb.manage','reports.view',
                 'companies.manage','zoho.view','ai.use');

-- Agent: work tickets + KB read + AI
INSERT INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE slug='agent'), id FROM permissions
  WHERE slug IN ('tickets.view','tickets.create','tickets.reply','tickets.assign',
                 'tickets.transfer','tickets.escalate','tickets.note','tickets.time',
                 'kb.view','ai.use');

-- Customer: portal + own tickets + KB + own finance
INSERT INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE slug='customer'), id FROM permissions
  WHERE slug IN ('portal.access','tickets.view','tickets.create','tickets.reply',
                 'kb.view','zoho.view');

-- Guest: KB only
INSERT INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE slug='guest'), id FROM permissions
  WHERE slug IN ('kb.view');

-- ---------- Ticket statuses (the 8 required) --------------------------------
INSERT INTO ticket_statuses (slug, name, colour, is_open_state, pauses_sla, is_resolved, sort_order) VALUES
  ('new',                'New',                '#6366f1', 1, 0, 0, 1),
  ('open',               'Open',               '#3b82f6', 1, 0, 0, 2),
  ('in_progress',        'In Progress',        '#0ea5e9', 1, 0, 0, 3),
  ('pending_customer',   'Pending Customer',   '#f59e0b', 1, 1, 0, 4),
  ('waiting_third_party','Waiting Third Party','#a855f7', 1, 1, 0, 5),
  ('resolved',           'Resolved',           '#22c55e', 0, 0, 1, 6),
  ('closed',             'Closed',             '#64748b', 0, 0, 1, 7),
  ('cancelled',          'Cancelled',          '#ef4444', 0, 0, 0, 8);

-- ---------- Priorities (the 5 required) -------------------------------------
INSERT INTO ticket_priorities (slug, name, colour, weight, sort_order) VALUES
  ('low',      'Low',      '#22c55e', 10, 1),
  ('medium',   'Medium',   '#3b82f6', 20, 2),
  ('high',     'High',     '#f59e0b', 30, 3),
  ('urgent',   'Urgent',   '#f97316', 40, 4),
  ('critical', 'Critical', '#ef4444', 50, 5);

-- ---------- SLA policy (Standard) + per-priority targets (minutes) ----------
INSERT INTO sla_policies (name, description, use_business_hours, is_active) VALUES
  ('Standard SLA', 'Default business-hours SLA', 1, 1);

INSERT INTO sla_targets (sla_policy_id, priority_id, first_response_minutes, resolution_minutes)
  SELECT p.id, pr.id, t.fr, t.res
  FROM (SELECT id FROM sla_policies WHERE name='Standard SLA') p
  JOIN (
    SELECT 'low' slug,      480 fr, 5760 res UNION ALL   -- 8h / 4 business days
    SELECT 'medium',        240,    2880 UNION ALL         -- 4h / 2 business days
    SELECT 'high',          120,    1440 UNION ALL         -- 2h / 1 business day
    SELECT 'urgent',         60,     480 UNION ALL         -- 1h / 8h
    SELECT 'critical',       30,     240                   -- 30m / 4h
  ) t
  JOIN ticket_priorities pr ON pr.slug = t.slug;

-- ---------- Departments (the 7 required) ------------------------------------
INSERT INTO departments (name, slug, email, sla_policy_id, is_public, is_active, sort_order)
  SELECT d.name, d.slug, d.email, (SELECT id FROM sla_policies WHERE name='Standard SLA'), 1, 1, d.so
  FROM (
    SELECT 'Sales'            name, 'sales'      slug, 'sales@example.com'      email, 1 so UNION ALL
    SELECT 'Accounts',              'accounts',       'accounts@example.com',       2 UNION ALL
    SELECT 'Support',               'support',        'support@example.com',        3 UNION ALL
    SELECT 'IT',                    'it',             'it@example.com',             4 UNION ALL
    SELECT 'Marketing',             'marketing',      'marketing@example.com',      5 UNION ALL
    SELECT 'Logistics',             'logistics',      'logistics@example.com',      6 UNION ALL
    SELECT 'Human Resources',       'human-resources','hr@example.com',             7
  ) d;

-- ---------- Business hours (Mon–Fri 09:00–17:00 for every department) --------
INSERT INTO business_hours (department_id, day_of_week, open_time, close_time)
  SELECT dep.id, dow.d,
         CASE WHEN dow.d BETWEEN 1 AND 5 THEN '09:00:00' ELSE NULL END,
         CASE WHEN dow.d BETWEEN 1 AND 5 THEN '17:00:00' ELSE NULL END
  FROM departments dep
  JOIN (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3
        UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) dow;

-- ---------- Email templates -------------------------------------------------
INSERT INTO email_templates (slug, name, subject, body_html, body_text, is_active) VALUES
  ('ticket_created', 'Ticket Created',
     'Ticket {{ticket.reference}} received: {{ticket.subject}}',
     '<p>Hi {{customer.first_name}},</p><p>We have received your ticket <strong>{{ticket.reference}}</strong> and our team will respond shortly.</p><p>You can track it here: <a href="{{ticket.url}}">{{ticket.url}}</a></p>',
     'Hi {{customer.first_name}}, we received your ticket {{ticket.reference}}. Track it: {{ticket.url}}', 1),
  ('ticket_agent_reply', 'Agent Reply',
     'Re: [{{ticket.reference}}] {{ticket.subject}}',
     '<p>Hi {{customer.first_name}},</p><p>{{reply.body}}</p><p><a href="{{ticket.url}}">View ticket</a></p>',
     '{{reply.body}} — View: {{ticket.url}}', 1),
  ('ticket_resolved', 'Ticket Resolved',
     'Your ticket {{ticket.reference}} has been resolved',
     '<p>Hi {{customer.first_name}},</p><p>Your ticket has been marked resolved. If the issue persists, reply to reopen it.</p><p>Please rate our support: <a href="{{ticket.rate_url}}">Rate</a></p>',
     'Ticket {{ticket.reference}} resolved. Rate us: {{ticket.rate_url}}', 1),
  ('password_reset', 'Password Reset',
     'Reset your NexusDesk password',
     '<p>We received a request to reset your password.</p><p><a href="{{reset.url}}">Reset password</a> (valid 60 minutes).</p><p>If you did not request this, ignore this email.</p>',
     'Reset your password: {{reset.url}} (valid 60 min)', 1),
  ('welcome_user', 'Welcome / Account Created',
     'Welcome to {{company.name}} support',
     '<p>Hi {{customer.first_name}},</p><p>An account has been created for you. <a href="{{login.url}}">Sign in</a>.</p>',
     'Welcome. Sign in: {{login.url}}', 1);

-- ---------- Core runtime settings -------------------------------------------
-- Values are stored as JSON. We use literal JSON text (not CAST/JSON_QUOTE)
-- so the seed is portable across MySQL 8 and MariaDB (MariaDB's JSON is an
-- alias for LONGTEXT and does not support CAST(x AS JSON)).
INSERT INTO settings (group_name, key_name, value, is_secret) VALUES
  ('general',  'app_name',        '"NexusDesk"',            0),
  ('general',  'timezone',        '"UTC"',                  0),
  ('general',  'date_format',     '"Y-m-d"',                0),
  ('general',  'locale',          '"en"',                   0),
  ('general',  'ticket_prefix',   '"NEXUS"',                0),
  ('branding', 'primary_color',   '"#4f46e5"',              0),
  ('branding', 'accent_color',    '"#0891b2"',              0),
  ('branding', 'logo_path',       '"/assets/img/logo.svg"', 0),
  ('branding', 'default_theme',   '"system"',               0),
  ('security', 'session_idle_minutes',   '30',              0),
  ('security', 'max_login_attempts',     '5',               0),
  ('security', 'lockout_minutes',        '15',              0),
  ('security', 'enforce_2fa_admins',     'false',           0),
  ('mail',     'from_name',       '"NexusDesk Support"',    0),
  ('mail',     'from_email',      '"support@example.com"',  0),
  ('mail',     'driver',          '"smtp"',                 0),
  ('zoho',     'enabled',         'false',                  0),
  ('ai',       'provider',        '"null"',                 0),
  ('ai',       'enabled',         'false',                  0);

-- ---------- Demo administrator (installer overwrites email + password) ------
-- Placeholder hash = password_hash('ChangeMe!123', PASSWORD_DEFAULT)
INSERT INTO users (role_id, first_name, last_name, email, password_hash, is_active, email_verified_at)
  VALUES ((SELECT id FROM roles WHERE slug='administrator'),
          'System', 'Administrator', 'admin@example.com',
          '$2y$12$e0MYzXyjpJS7Pd0RVvHwHe1XoP5G0Q5uJ0h7bJf3vQ2n8yYk9wEa',
          1, NOW());

-- =============================================================================
--  END OF SEED
-- =============================================================================
