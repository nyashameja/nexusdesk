<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<int,array<string,mixed>> $monitors */
/** @var array<string,int> $counts */
/** @var \ParagonHostOps\Services\Auth $auth */
$this->layout('layouts.app');

$dot = static fn (string $s): string => match ($s) {
    'online' => 'ok', 'slow' => 'warn', 'offline' => 'danger', 'ssl_problem' => 'danger', default => 'neutral',
};
$label = static fn (string $s): string => match ($s) {
    'ssl_problem' => 'SSL problem', default => ucfirst($s),
};
$canManage = $auth->can('uptime.manage');
?>
<div class="pg-page-head">
    <div>
        <h1>Uptime</h1>
        <p>Lightweight HTTP monitoring. Run checks manually or via a cPanel Cron Job — no permanent worker required.</p>
    </div>
    <div class="flex gap-2">
        <?php if ($canManage): ?>
            <form method="post" action="<?= e(url('/uptime/check-all')) ?>" style="margin:0"><?= csrf_field() ?><button class="pg-btn" type="submit">Check all now</button></form>
            <a class="pg-btn primary" href="<?= e(url('/uptime/create')) ?>">+ Add monitor</a>
        <?php endif; ?>
    </div>
</div>

<div class="pg-grid cols-4 mb-3">
    <div class="pg-stat"><div class="label">Online</div><div class="value" style="color:var(--pg-success)"><?= (int) ($counts['online'] ?? 0) ?></div></div>
    <div class="pg-stat"><div class="label">Slow</div><div class="value" style="color:var(--pg-warning)"><?= (int) ($counts['slow'] ?? 0) ?></div></div>
    <div class="pg-stat"><div class="label">Offline</div><div class="value" style="color:var(--pg-danger)"><?= (int) ($counts['offline'] ?? 0) ?></div></div>
    <div class="pg-stat"><div class="label">SSL problem</div><div class="value" style="color:var(--pg-danger)"><?= (int) ($counts['ssl_problem'] ?? 0) ?></div></div>
</div>

<div class="pg-card">
    <div class="pg-table-wrap">
        <?php if (empty($monitors)): ?>
            <div class="pg-empty"><span class="ic">◉</span><h3>No monitors</h3><p>Add a website URL to start monitoring its availability.</p></div>
        <?php else: ?>
            <table class="pg-table">
                <thead><tr><th>Monitor</th><th>Status</th><th>Code</th><th>Response</th><th>Fails</th><th>Last checked</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($monitors as $m): ?>
                    <tr>
                        <td>
                            <strong><?= e($m['label']) ?></strong>
                            <?php if ((int) $m['enabled'] === 0): ?><span class="pg-badge neutral" style="margin-left:6px">Disabled</span><?php endif; ?>
                            <div class="pg-muted" style="font-size:11px"><?= e($m['url']) ?></div>
                        </td>
                        <td><span class="pg-badge <?= $dot((string) $m['current_status']) ?>"><span class="pg-dot <?= $dot((string) $m['current_status']) ?>"></span> <?= e($label((string) $m['current_status'])) ?></span></td>
                        <td class="pg-soft"><?= $m['last_status_code'] !== null ? (int) $m['last_status_code'] : '—' ?></td>
                        <td class="pg-soft"><?= $m['last_response_ms'] !== null ? (int) $m['last_response_ms'] . ' ms' : '—' ?></td>
                        <td class="pg-soft"><?= (int) $m['failure_count'] ?></td>
                        <td class="pg-soft"><?= !empty($m['last_checked_at']) ? e(date('d M H:i', strtotime((string) $m['last_checked_at'] . ' UTC'))) : 'never' ?></td>
                        <?php if ($canManage): ?>
                            <td class="text-right">
                                <div class="flex gap-2" style="justify-content:flex-end">
                                    <form method="post" action="<?= e(url('/uptime/' . (int) $m['id'] . '/check')) ?>" style="margin:0"><?= csrf_field() ?><button class="pg-btn ghost" type="submit" style="padding:4px 10px">Check</button></form>
                                    <a class="pg-btn ghost" href="<?= e(url('/uptime/' . (int) $m['id'] . '/edit')) ?>" style="padding:4px 10px">Edit</a>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="pg-card mt-3">
    <div class="pg-card-head">Scheduled checks</div>
    <div class="pg-card-body">
        <p class="pg-soft mb-1">Run checks automatically every 10 minutes via a cPanel Cron Job (CLI or the secret-protected web endpoint):</p>
        <pre style="background:#0f1b2d;color:#c7d2e0;padding:12px 14px;border-radius:8px;overflow-x:auto;font-size:12.5px;margin:0">*/10 * * * * /usr/local/bin/php <?= e(base_path('bin/uptime.php')) ?> >/dev/null 2>&1
# or (web):  curl -s "<?= e(url('/cron.php?job=uptime&token=YOUR_CRON_SECRET')) ?>"</pre>
    </div>
</div>
