<?php /** @var array $notifications */ ?>
<div class="page-head">
    <div><h1>Notifications</h1></div>
    <?php if ($notifications !== []): ?>
        <form method="post" action="/notifications/read-all">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost btn-sm">Mark all read</button>
        </form>
    <?php endif; ?>
</div>

<div class="card"><div class="card-body" style="padding:0">
    <?php if ($notifications === []): ?>
        <div class="empty">You're all caught up. 🎉</div>
    <?php else: foreach ($notifications as $n): ?>
        <?php $unread = $n['read_at'] === null; ?>
        <a href="<?= e($n['url'] ?? '#') ?>" style="display:flex;gap:14px;align-items:flex-start;padding:14px 18px;border-bottom:1px solid var(--border);color:inherit;text-decoration:none;<?= $unread ? 'background:var(--brand-soft)' : '' ?>">
            <span style="width:8px;height:8px;border-radius:50%;background:<?= $unread ? 'var(--brand)' : 'var(--border)' ?>;margin-top:6px;flex:none"></span>
            <span style="flex:1">
                <strong style="color:var(--ink)"><?= e($n['title']) ?></strong>
                <?php if (!empty($n['body'])): ?><div class="muted" style="font-size:13px"><?= e($n['body']) ?></div><?php endif; ?>
                <div class="muted tnum" style="font-size:11px;margin-top:3px"><?= e($n['created_at']) ?></div>
            </span>
        </a>
    <?php endforeach; endif; ?>
</div></div>
