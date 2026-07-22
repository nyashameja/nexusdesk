<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<int,array<string,mixed>> $notifications */
$this->layout('layouts.app');
$sev = static fn (string $s): string => match ($s) {
    'danger' => 'danger', 'warning' => 'warn', 'success' => 'ok', default => 'info',
};
?>
<div class="pg-page-head">
    <div>
        <h1>Notifications</h1>
        <p>System alerts from synchronisation, uptime and security signals.</p>
    </div>
    <?php if (!empty($notifications)): ?>
        <form method="post" action="<?= e(url('/notifications/read')) ?>" style="margin:0"><?= csrf_field() ?><button class="pg-btn" type="submit">Mark all read</button></form>
    <?php endif; ?>
</div>

<div class="pg-card">
    <div class="pg-card-body">
        <?php if (empty($notifications)): ?>
            <div class="pg-empty"><span class="ic">🔔</span><h3>No notifications</h3><p>You're all caught up.</p></div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <div style="display:flex;gap:12px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--pg-border);<?= empty($n['read_at']) ? 'background:#fbfcfe' : 'opacity:.72' ?>">
                    <span class="pg-badge <?= $sev((string) $n['severity']) ?>" style="margin-top:2px"><?= e(ucfirst((string) $n['type'])) ?></span>
                    <div style="flex:1">
                        <div style="font-weight:600;font-size:13.5px"><?= e($n['title']) ?></div>
                        <?php if (!empty($n['body'])): ?><div class="pg-soft" style="font-size:12.5px"><?= e($n['body']) ?></div><?php endif; ?>
                    </div>
                    <div class="pg-muted" style="font-size:11px;white-space:nowrap"><?= e(date('d M H:i', strtotime((string) $n['created_at'] . ' UTC'))) ?>
                        <?php if (empty($n['read_at'])): ?><span class="pg-dot warn" style="margin-left:6px"></span><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
