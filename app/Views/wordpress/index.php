<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<int,array<string,mixed>> $sites */
/** @var array<string,int> $counts */
/** @var string $search, $status */
/** @var array<int,string> $statuses */
/** @var int $page, $pages, $total */
/** @var \ParagonHostOps\Services\Auth $auth */
$this->layout('layouts.app');

$badge = static fn (string $s): string => match ($s) {
    'healthy' => 'ok', 'updates_required' => 'warn', 'maintenance_overdue', 'backup_overdue' => 'warn',
    'security_review' => 'danger', default => 'neutral',
};
$label = static fn (string $s): string => ucfirst(str_replace('_', ' ', $s));
?>
<div class="pg-page-head">
    <div>
        <h1>WordPress</h1>
        <p><?= (int) $total ?> site(s) in the registry. Data is entered manually in Version&nbsp;1.</p>
    </div>
    <?php if ($auth->can('wordpress.manage')): ?>
        <a class="pg-btn primary" href="<?= e(url('/wordpress/create')) ?>">+ Add site</a>
    <?php endif; ?>
</div>

<div class="pg-card mb-3">
    <div class="pg-card-body">
        <form method="get" action="<?= e(url('/wordpress')) ?>" class="flex gap-2 flex-wrap" style="align-items:flex-end">
            <div class="pg-field" style="margin:0;min-width:260px"><label>Search</label><input class="pg-input" name="q" value="<?= e($search) ?>" placeholder="Site name or URL"></div>
            <div class="pg-field" style="margin:0"><label>Status</label>
                <select class="pg-input" name="status"><option value="">All</option>
                    <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($label($s)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button class="pg-btn primary" type="submit">Filter</button>
            <?php if ($search !== '' || $status !== ''): ?><a class="pg-btn ghost" href="<?= e(url('/wordpress')) ?>">Reset</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="pg-card">
    <div class="pg-table-wrap">
        <?php if (empty($sites)): ?>
            <div class="pg-empty"><span class="ic">W</span><h3>No WordPress sites</h3><p>Add sites to track versions, backups and maintenance plans.</p></div>
        <?php else: ?>
            <table class="pg-table">
                <thead><tr><th>Site</th><th>Client</th><th>WP</th><th>PHP</th><th>Last backup</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($sites as $w): ?>
                    <tr>
                        <td>
                            <?php if ($auth->can('wordpress.manage')): ?><a href="<?= e(url('/wordpress/' . (int) $w['id'] . '/edit')) ?>"><strong><?= e($w['name']) ?></strong></a><?php else: ?><strong><?= e($w['name']) ?></strong><?php endif; ?>
                            <?php if (!empty($w['url'])): ?><div class="pg-muted" style="font-size:11px"><?= e($w['url']) ?></div><?php endif; ?>
                        </td>
                        <td class="pg-soft"><?= e($w['client_name'] ?: '—') ?></td>
                        <td class="pg-soft"><?= e($w['wp_version'] ?? '—') ?></td>
                        <td class="pg-soft"><?= e($w['php_version'] ?? '—') ?></td>
                        <td class="pg-soft"><?= !empty($w['last_backup_at']) ? e(date('d M Y', strtotime((string) $w['last_backup_at']))) : '—' ?></td>
                        <td><span class="pg-badge <?= $badge((string) $w['wp_status']) ?>"><?= e($label((string) $w['wp_status'])) ?></span></td>
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
            <?php $qs = ($search !== '' ? '&q=' . urlencode($search) : '') . ($status !== '' ? '&status=' . urlencode($status) : ''); ?>
            <?php if ($page > 1): ?><a class="pg-btn" href="<?= e(url('/wordpress?page=' . ($page - 1) . $qs)) ?>">Previous</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="pg-btn" href="<?= e(url('/wordpress?page=' . ($page + 1) . $qs)) ?>">Next</a><?php endif; ?>
        </div>
    </div>
<?php endif; ?>
