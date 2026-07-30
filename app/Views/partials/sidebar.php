<?php
/** @var \ParagonHostOps\Services\Auth $auth */
/** @var array{whmConfigured:bool, lastSync:?string, server:?string} $shell */
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$shell   = $shell ?? ['whmConfigured' => false, 'lastSync' => null, 'server' => null];

/**
 * Navigation groups: label => [[path, label, icon-glyph, permission], ...].
 * Items the current user cannot access are hidden — but every route is ALSO
 * protected server-side, so hiding is a convenience, not the security boundary.
 */
$groups = [
    'Operations' => [
        ['/dashboard', 'Dashboard',        '▦', 'dashboard.view'],
        ['/accounts',  'Hosting Accounts', '▤', 'accounts.view'],
        ['/clients',   'Clients',          '☰', 'clients.view'],
        ['/domains',   'Domains',          '◈', 'domains.view'],
        ['/ssl',       'SSL Centre',       '⛨', 'ssl.view'],
        ['/email',     'Email Centre',     '✉', 'email.view'],
        ['/wordpress', 'WordPress',        'W', 'wordpress.view'],
        ['/uptime',    'Uptime',           '◉', 'uptime.view'],
    ],
    'Business' => [
        ['/finance',   'Finance',          '$', 'finance.view'],
        ['/reports',   'Reports',          '▬', 'reports.view'],
        ['/security',  'Security',         '⚿', 'security.view'],
    ],
    'System' => [
        ['/sync',            'Synchronisation', '⟳', 'sync.view'],
        ['/settings/alerts', 'Alerts',          '🔔', 'settings.view'],
        ['/audit-logs',      'Audit Logs',      '❐', 'audit.view'],
        ['/settings/whm',    'Settings',        '⚙', 'settings.view'],
    ],
];

/** Longest-prefix match so /settings/alerts doesn't also light up /settings/whm. */
$isActive = static function (string $path) use ($current): bool {
    if ($path === '/dashboard') {
        return $current === $path;
    }
    return $current === $path || str_starts_with($current, $path . '/');
};
?>
<aside class="pg-sidebar" id="pg-sidebar" aria-label="Main navigation">
    <div class="pg-brand">
        <span class="pg-mark" aria-hidden="true">P</span>
        <span class="pg-brand-text">
            <span class="pg-logo"><span class="pg-logo-name">Paragon HostOps</span></span>
            <small><?= e($tagline ?? 'Hosting Management & Operations') ?></small>
        </span>
    </div>

    <nav class="pg-nav">
        <?php foreach ($groups as $groupLabel => $items): ?>
            <?php
            $visible = array_filter($items, static fn (array $i): bool => $auth->can($i[3]));
            if ($visible === []) {
                continue;
            }
            ?>
            <div class="pg-nav-label"><?= e($groupLabel) ?></div>
            <?php foreach ($visible as [$path, $label, $icon, $perm]): ?>
                <?php $active = $isActive($path); ?>
                <a href="<?= e(url($path)) ?>"
                   class="<?= $active ? 'active' : '' ?>"
                   <?= $active ? 'aria-current="page"' : '' ?>>
                    <span class="ic" aria-hidden="true"><?= $icon ?></span>
                    <span class="pg-nav-text"><?= e($label) ?></span>
                    <?php if ($path === '/settings/alerts' && !empty($notifUnread)): ?>
                        <span class="pg-nav-count alert"><?= (int) $notifUnread > 99 ? '99+' : (int) $notifUnread ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="pg-sidebar-foot">
        <div class="row">
            <span class="pg-dot <?= $shell['whmConfigured'] ? 'ok' : 'warn' ?>" aria-hidden="true"></span>
            <span>WHM <strong><?= $shell['whmConfigured'] ? 'connected' : 'not set up' ?></strong></span>
        </div>
        <div class="row">
            <span class="pg-dot <?= $shell['lastSync'] ? 'ok' : 'neutral' ?>" aria-hidden="true"></span>
            <span>Synced <strong><?= e(time_ago($shell['lastSync'])) ?></strong></span>
        </div>
        <button type="button" class="pg-sidebar-collapse" data-toggle="sidebar-collapse"
                aria-controls="pg-sidebar" aria-expanded="true">
            <span aria-hidden="true">⇤</span><span>Collapse menu</span>
        </button>
    </div>
</aside>
