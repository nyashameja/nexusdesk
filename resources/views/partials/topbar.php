<?php
/** @var \App\Models\User|null $user */
$unread = 0;
if ($user) {
    try {
        $unread = app(\App\Repositories\Contracts\NotificationRepositoryInterface::class)->unreadCount($user->id);
    } catch (\Throwable) {
        $unread = 0;
    }
}
?>
<header class="topbar">
    <button class="icnbtn menu-toggle" data-menu-toggle aria-label="Menu">≡</button>
    <form class="search" action="/desk/tickets" method="get" role="search">
        <span>⌕</span>
        <input type="search" name="q" placeholder="Search tickets, people, invoices…" aria-label="Search">
    </form>
    <button class="icnbtn" data-theme-toggle title="Toggle theme">🌗</button>
    <a class="icnbtn" href="/notifications" title="Notifications" data-notif-bell>🔔<span class="badge-dot" data-notif-count <?= $unread > 0 ? '' : 'hidden' ?>><?= (int) min($unread, 99) ?></span></a>
    <?php if ($user): ?>
        <div class="ava" title="<?= e($user->fullName()) ?>"><?= e($user->initials()) ?></div>
    <?php endif; ?>
</header>
