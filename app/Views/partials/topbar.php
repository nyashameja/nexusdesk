<?php
/** @var \ParagonHostOps\Services\Auth $auth */
/** @var array{whmConfigured:bool, lastSync:?string, server:?string} $shell */
$name     = $auth->name();
$initials = strtoupper(substr($name, 0, 1) . (str_contains($name, ' ') ? substr(strstr($name, ' '), 1, 1) : ''));
$shell    = $shell ?? ['whmConfigured' => false, 'lastSync' => null, 'server' => null];
$unread   = (int) ($notifUnread ?? 0);
?>
<header class="pg-topbar">
    <button class="pg-hamburger" type="button" data-toggle="sidebar"
            aria-controls="pg-sidebar" aria-expanded="false">
        <span aria-hidden="true">☰</span>
        <span class="pg-sr-only">Open navigation menu</span>
    </button>

    <form class="pg-search" action="<?= e(url('/accounts')) ?>" method="get" role="search">
        <span class="pg-soft" aria-hidden="true">⌕</span>
        <label class="pg-sr-only" for="pg-global-search">Search hosting accounts</label>
        <input type="search" id="pg-global-search" name="q"
               placeholder="Search accounts, domains, clients…">
    </form>

    <div class="pg-spacer"></div>

    <?php if ($shell['server'] !== null): ?>
        <div class="pg-topmeta" title="Connected WHM server">
            <span class="pg-dot <?= $shell['whmConfigured'] ? 'ok' : 'warn' ?>" aria-hidden="true"></span>
            <span><?= e($shell['server']) ?></span>
        </div>
    <?php endif; ?>

    <div class="pg-topmeta" title="Time of the last successful synchronisation">
        <span aria-hidden="true">⟳</span>
        <span>Synced <?= e(time_ago($shell['lastSync'])) ?></span>
    </div>

    <a href="<?= e(url('/notifications')) ?>" class="pg-iconbtn">
        <span aria-hidden="true">🔔</span>
        <span class="pg-sr-only">
            Notifications<?= $unread > 0 ? ' (' . $unread . ' unread)' : '' ?>
        </span>
        <?php if ($unread > 0): ?>
            <span class="pg-count" aria-hidden="true"><?= $unread > 99 ? '99+' : $unread ?></span>
        <?php endif; ?>
    </a>

    <div class="pg-user">
        <div class="text-right" style="line-height:1.3">
            <div style="font-weight:600;color:var(--color-text)"><?= e($name) ?></div>
            <div style="font-size:11px" class="pg-muted"><?= e(ucwords(str_replace('_', ' ', $auth->roles()[0] ?? 'user'))) ?></div>
        </div>
        <span class="pg-avatar" aria-hidden="true"><?= e($initials) ?></span>
        <form action="<?= e(url('/logout')) ?>" method="post" style="margin:0">
            <?= csrf_field() ?>
            <button class="pg-btn ghost sm" type="submit">Sign out</button>
        </form>
    </div>
</header>
