<?php
/** @var \ParagonHostOps\Services\Auth $auth */
$name = $auth->name();
$initials = strtoupper(substr($name, 0, 1) . (str_contains($name, ' ') ? substr(strstr($name, ' '), 1, 1) : ''));
?>
<header class="pg-topbar">
    <button class="pg-hamburger" data-toggle="sidebar" aria-label="Toggle menu">☰</button>

    <form class="pg-search" action="<?= e(url('/accounts')) ?>" method="get" role="search">
        <span class="pg-soft">⌕</span>
        <input type="text" name="q" placeholder="Search accounts, domains, clients…" aria-label="Global search">
    </form>

    <div class="pg-spacer"></div>

    <a href="<?= e(url('/notifications')) ?>" class="pg-btn ghost" title="Notifications" style="position:relative;padding:8px 11px">
        🔔
        <?php if (!empty($notifUnread)): ?>
            <span style="position:absolute;top:2px;right:2px;background:var(--pg-danger);color:#fff;border-radius:10px;font-size:10px;font-weight:700;padding:1px 5px;line-height:1.4"><?= (int) $notifUnread > 99 ? '99+' : (int) $notifUnread ?></span>
        <?php endif; ?>
    </a>

    <div class="pg-user">
        <div class="text-right" style="line-height:1.25">
            <div style="font-weight:600;color:var(--pg-text)"><?= e($name) ?></div>
            <div style="font-size:11px" class="pg-muted"><?= e(ucwords(str_replace('_', ' ', $auth->roles()[0] ?? 'user'))) ?></div>
        </div>
        <span class="pg-avatar"><?= e($initials) ?></span>
        <form action="<?= e(url('/logout')) ?>" method="post" style="margin:0">
            <?= csrf_field() ?>
            <button class="pg-btn ghost" type="submit" title="Sign out">Logout</button>
        </form>
    </div>
</header>
