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
    <button class="icnbtn" title="Notifications">🔔<?php if ($unread > 0): ?><span class="badge-dot"><?= (int) min($unread, 99) ?></span><?php endif; ?></button>
    <?php if ($user): ?>
        <div class="ava" title="<?= e($user->fullName()) ?>"><?= e($user->initials()) ?></div>
    <?php endif; ?>
</header>
