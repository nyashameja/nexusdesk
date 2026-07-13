<?php
/** @var \App\Models\User|null $user */
/** @var string $active */
$appName = $appName ?? setting('general.app_name', 'NexusDesk');
$role = $user?->roleSlug ?? 'guest';

/** @param string $key */
$is = static fn (string $key): string => ($active ?? '') === $key ? ' active' : '';

/** @return array<int,array{href:string,label:string,icon:string,key:string}> */
$nav = [];
if ($role === 'customer') {
    $nav = [
        ['href' => '/portal', 'label' => 'Dashboard', 'icon' => '◑', 'key' => 'dashboard'],
        ['href' => '/portal/tickets', 'label' => 'My tickets', 'icon' => '▤', 'key' => 'tickets'],
        ['href' => '/kb', 'label' => 'Knowledge base', 'icon' => '▢', 'key' => 'kb'],
    ];
} elseif (in_array($role, ['agent', 'manager', 'administrator'], true)) {
    $nav = [
        ['href' => '/desk', 'label' => 'Dashboard', 'icon' => '◑', 'key' => 'dashboard'],
        ['href' => '/desk/tickets', 'label' => 'Tickets', 'icon' => '▤', 'key' => 'tickets'],
        ['href' => '/desk/kb', 'label' => 'Knowledge base', 'icon' => '▢', 'key' => 'kb'],
        ['href' => '/desk/search', 'label' => 'Search', 'icon' => '⌕', 'key' => 'search'],
    ];
}
?>
<aside class="sidebar" id="sidebar">
    <a href="/" class="logo"><span class="mark"></span><span class="brandname"><?= e($appName) ?></span></a>

    <?php foreach ($nav as $item): ?>
        <a class="navlink<?= $is($item['key']) ?>" href="<?= e($item['href']) ?>">
            <span class="g"><?= $item['icon'] ?></span><?= e($item['label']) ?>
        </a>
    <?php endforeach; ?>

    <?php if (in_array($role, ['manager', 'administrator'], true)): ?>
        <div class="group">Management</div>
        <a class="navlink<?= $is('manage') ?>" href="/manage"><span class="g">◔</span>Reports</a>
    <?php endif; ?>

    <?php if ($role === 'administrator'): ?>
        <div class="group">Administration</div>
        <a class="navlink<?= $is('admin_dashboard') ?>" href="/admin"><span class="g">⚙</span>Overview</a>
        <a class="navlink<?= $is('users') ?>" href="/admin/users"><span class="g">◔</span>Users</a>
        <a class="navlink<?= $is('departments') ?>" href="/admin/departments"><span class="g">▧</span>Departments</a>
        <a class="navlink<?= $is('settings') ?>" href="/admin/settings/branding"><span class="g">◐</span>Settings</a>
    <?php endif; ?>

    <div class="spacer"></div>
    <form method="post" action="/logout">
        <?= csrf_field() ?>
        <button type="submit" class="navlink" style="width:100%;border:0;background:transparent;cursor:pointer;text-align:left">
            <span class="g">⏻</span>Sign out
        </button>
    </form>
</aside>
