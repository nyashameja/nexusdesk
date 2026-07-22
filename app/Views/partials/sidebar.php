<?php
/** @var \ParagonHostOps\Services\Auth $auth */
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

/**
 * Navigation items: [path, label, icon-glyph, permission].
 * Items the current user cannot access are hidden — but every route is ALSO
 * protected server-side, so hiding is a convenience, not the security boundary.
 */
$nav = [
    ['/dashboard', 'Overview',         '▦', 'dashboard.view'],
    ['/accounts',  'Hosting Accounts', '▤', 'accounts.view'],
    ['/clients',   'Clients',          '☰', 'clients.view'],
    ['/domains',   'Domains',          '◈', 'domains.view'],
    ['/ssl',       'SSL Centre',       '⛨', 'ssl.view'],
    ['/email',     'Email Centre',     '✉', 'email.view'],
    ['/wordpress', 'WordPress',        'W', 'wordpress.view'],
    ['/uptime',    'Uptime',           '◉', 'uptime.view'],
    ['/security',  'Security',         '⚿', 'security.view'],
    ['/finance',   'Finance',          '$', 'finance.view'],
    ['/reports',   'Reports',          '▬', 'reports.view'],
    ['/sync',      'Synchronisation',  '⟳', 'sync.view'],
    ['/audit-logs','Audit Logs',       '❐', 'audit.view'],
    ['/settings/whm', 'Settings',      '⚙', 'settings.view'],
];
?>
<aside class="pg-sidebar">
    <div class="pg-brand">
        <span class="pg-logo"><span class="pg-mark">P</span> Paragon HostOps</span>
        <small><?= e($tagline ?? 'Hosting Management &amp; Operations') ?></small>
    </div>
    <nav class="pg-nav">
        <div class="pg-nav-label">Operations</div>
        <?php foreach ($nav as [$path, $label, $icon, $perm]): ?>
            <?php if (!$auth->can($perm)) { continue; } ?>
            <a href="<?= e(url($path)) ?>" class="<?= str_starts_with($current, $path) && $path !== '/dashboard' || $current === $path ? 'active' : '' ?>">
                <span class="ic"><?= $icon ?></span> <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
