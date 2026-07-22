<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<int,array<string,mixed>> $logs */
/** @var array<int,string> $actions */
/** @var string $action */
/** @var int $page, $pages, $total */
$this->layout('layouts.app');
?>
<div class="pg-page-head">
    <div>
        <h1>Audit Logs</h1>
        <p><?= (int) $total ?> recorded action(s). Secrets are never logged.</p>
    </div>
</div>

<div class="pg-card mb-3">
    <div class="pg-card-body">
        <form method="get" action="<?= e(url('/audit-logs')) ?>" class="flex gap-2" style="align-items:flex-end">
            <div class="pg-field" style="margin:0;min-width:240px"><label>Action</label>
                <select class="pg-input" name="action"><option value="">All actions</option>
                    <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button class="pg-btn primary" type="submit">Filter</button>
            <?php if ($action !== ''): ?><a class="pg-btn ghost" href="<?= e(url('/audit-logs')) ?>">Reset</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="pg-card">
    <div class="pg-table-wrap">
        <?php if (empty($logs)): ?>
            <div class="pg-empty"><span class="ic">❐</span><h3>No entries</h3><p>Audit activity will appear here.</p></div>
        <?php else: ?>
            <table class="pg-table">
                <thead><tr><th>When (UTC)</th><th>User</th><th>Action</th><th>Description</th><th>Entity</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="pg-soft" style="white-space:nowrap"><?= e(date('d M Y H:i', strtotime((string) $l['created_at'] . ' UTC'))) ?></td>
                        <td class="pg-soft"><?= e($l['user_name'] ?? 'System') ?></td>
                        <td><span class="pg-badge neutral"><?= e($l['action']) ?></span></td>
                        <td class="pg-soft"><?= e($l['description'] ?? '') ?></td>
                        <td class="pg-soft"><?= $l['entity_type'] ? e($l['entity_type'] . ' #' . $l['entity_id']) : '—' ?></td>
                        <td class="pg-soft"><?= e($l['ip_address'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php if ($pages > 1): ?>
    <div class="flex gap-2 mt-2 items-center justify-between">
        <span class="pg-soft" style="font-size:12.5px">Page <?= $page ?> of <?= $pages ?></span>
        <div class="flex gap-2">
            <?php $qs = $action !== '' ? '&action=' . urlencode($action) : ''; ?>
            <?php if ($page > 1): ?><a class="pg-btn" href="<?= e(url('/audit-logs?page=' . ($page - 1) . $qs)) ?>">Previous</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="pg-btn" href="<?= e(url('/audit-logs?page=' . ($page + 1) . $qs)) ?>">Next</a><?php endif; ?>
        </div>
    </div>
<?php endif; ?>
