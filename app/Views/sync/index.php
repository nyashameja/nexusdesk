<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<int,array<string,mixed>> $history */
/** @var array<int,array<string,mixed>> $capabilities */
/** @var bool $configured */
$this->layout('layouts.app');

$statusBadge = static function (string $status): string {
    return match ($status) {
        'completed' => '<span class="pg-badge ok">Completed</span>',
        'partial'   => '<span class="pg-badge warn">Partial</span>',
        'running'   => '<span class="pg-badge info">Running</span>',
        'failed'    => '<span class="pg-badge danger">Failed</span>',
        default     => '<span class="pg-badge neutral">' . e(ucfirst($status)) . '</span>',
    };
};
$capBadge = static function (string $status): string {
    $cls = match ($status) {
        'available' => 'ok',
        'permission_denied', 'auth_failed' => 'warn',
        'server_error' => 'danger',
        default => 'neutral',
    };
    return $cls;
};
?>
<div class="pg-page-head">
    <div>
        <h1>Synchronisation</h1>
        <p>Pull read-only data from WHM into the local cache. The dashboard reads only cached data.</p>
    </div>
    <div class="flex items-center gap-2">
        <a class="pg-btn" href="<?= e(url('/sync/history')) ?>">View full history</a>
        <?php if ($auth->can('sync.run')): ?>
            <form action="<?= e(url('/sync/run')) ?>" method="post" style="margin:0">
                <?= csrf_field() ?>
                <button class="pg-btn primary" type="submit" <?= $configured ? '' : 'disabled' ?>>⟳ Synchronise now</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!$configured): ?>
    <div class="pg-alert warn"><span>WHM is not configured. Set the credentials in <code>.env</code> and test the connection in <a href="<?= e(url('/settings/whm')) ?>">Settings → WHM</a>.</span></div>
<?php endif; ?>

<div class="pg-grid cols-2 mb-3">
    <div class="pg-card">
        <div class="pg-card-head">Recent runs</div>
        <div class="pg-table-wrap">
            <?php if (empty($history)): ?>
                <div class="pg-empty"><span class="ic">⟳</span><h3>No synchronisations yet</h3><p>Run one now or schedule it via cron.</p></div>
            <?php else: ?>
                <table class="pg-table">
                    <thead><tr><th>Type</th><th>Status</th><th>Processed</th><th>Finished</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $run): ?>
                        <tr>
                            <td><?= e(ucfirst((string) $run['sync_type'])) ?></td>
                            <td><?= $statusBadge((string) $run['status']) ?></td>
                            <td class="pg-soft"><?= (int) $run['records_processed'] ?> (<?= (int) $run['records_created'] ?> new / <?= (int) $run['records_updated'] ?> upd<?= (int) $run['records_failed'] > 0 ? ' / ' . (int) $run['records_failed'] . ' failed' : '' ?>)</td>
                            <td class="pg-soft"><?= $run['finished_at'] ? e(date('d M H:i', strtotime((string) $run['finished_at'] . ' UTC'))) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="pg-card">
        <div class="pg-card-head">API capabilities</div>
        <div class="pg-table-wrap">
            <?php if (empty($capabilities)): ?>
                <div class="pg-empty"><span class="ic">⚙</span><h3>Not yet recorded</h3><p>Capabilities are recorded during synchronisation, or check them in Settings → WHM.</p></div>
            <?php else: ?>
                <table class="pg-table">
                    <thead><tr><th>Function</th><th>Status</th><th>Checked</th></tr></thead>
                    <tbody>
                    <?php foreach ($capabilities as $cap): ?>
                        <tr>
                            <td><code><?= e((string) $cap['function_name']) ?></code></td>
                            <td><span class="pg-badge <?= $capBadge((string) $cap['status']) ?>"><?= e((string) $cap['label']) ?></span></td>
                            <td class="pg-soft"><?= $cap['checked_at'] ? e(date('d M H:i', strtotime((string) $cap['checked_at'] . ' UTC'))) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="pg-card">
    <div class="pg-card-head">Scheduling</div>
    <div class="pg-card-body">
        <p class="pg-soft mb-1">Run synchronisation automatically with a cPanel Cron Job (no permanent worker required):</p>
        <pre style="background:#0f1b2d;color:#c7d2e0;padding:12px 14px;border-radius:8px;overflow-x:auto;font-size:12.5px;margin:0"># Every night at 02:00 (CLI)
0 2 * * * /usr/local/bin/php <?= e(base_path('bin/sync.php')) ?> >/dev/null 2>&1</pre>
    </div>
</div>
