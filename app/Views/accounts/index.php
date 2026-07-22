<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<int,array<string,mixed>> $accounts */
/** @var string $search */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
$this->layout('layouts.app');

$pct = static function ($used, $limit): int {
    $used = (float) $used; $limit = (float) $limit;
    return $limit > 0 ? (int) min(100, round(($used / $limit) * 100)) : 0;
};
$bar = static fn (int $p): string => $p >= 90 ? 'danger' : ($p >= 80 ? 'warn' : '');
?>
<div class="pg-page-head">
    <div>
        <h1>Hosting Accounts</h1>
        <p><?= (int) $total ?> cached account<?= $total === 1 ? '' : 's' ?>. Data is read-only in Version&nbsp;1.</p>
    </div>
</div>

<div class="pg-card mb-3">
    <div class="pg-card-body">
        <form method="get" action="<?= e(url('/accounts')) ?>" class="flex gap-2 flex-wrap">
            <input class="pg-input" style="max-width:320px" type="text" name="q" value="<?= e($search) ?>" placeholder="Search by domain, username or email">
            <button class="pg-btn primary" type="submit">Search</button>
            <?php if ($search !== ''): ?><a class="pg-btn ghost" href="<?= e(url('/accounts')) ?>">Clear</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="pg-card">
    <div class="pg-table-wrap">
        <?php if (empty($accounts)): ?>
            <div class="pg-empty">
                <span class="ic">▤</span>
                <h3>No accounts found</h3>
                <p><?= $search !== '' ? 'No accounts match your search.' : 'Run a synchronisation to populate cached account data.' ?></p>
            </div>
        <?php else: ?>
            <table class="pg-table">
                <thead>
                    <tr>
                        <th>Domain</th><th>Username</th><th>Package</th><th>Disk</th><th>Bandwidth</th><th>Status</th><th>SSL</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($accounts as $a):
                    $dp = $pct($a['disk_used_mb'] ?? 0, $a['disk_limit_mb'] ?? 0);
                    $bp = $pct($a['bandwidth_used_mb'] ?? 0, $a['bandwidth_limit_mb'] ?? 0);
                ?>
                    <tr>
                        <td><strong><?= e($a['domain'] ?? '') ?></strong></td>
                        <td class="pg-soft"><?= e($a['username'] ?? '') ?></td>
                        <td class="pg-soft"><?= e($a['package'] ?? '—') ?></td>
                        <td><div class="pg-progress" title="<?= $dp ?>%"><span class="<?= $bar($dp) ?>" style="width:<?= $dp ?>%"></span></div></td>
                        <td><div class="pg-progress" title="<?= $bp ?>%"><span class="<?= $bar($bp) ?>" style="width:<?= $bp ?>%"></span></div></td>
                        <td>
                            <?php if ((int) ($a['suspended'] ?? 0) === 1): ?>
                                <span class="pg-badge danger">Suspended</span>
                            <?php else: ?>
                                <span class="pg-badge ok">Active</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="pg-badge neutral"><?= e(ucfirst((string) ($a['ssl_status'] ?? 'unknown'))) ?></span></td>
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
            <?php if ($page > 1): ?><a class="pg-btn" href="<?= e(url('/accounts?page=' . ($page - 1) . ($search !== '' ? '&q=' . urlencode($search) : ''))) ?>">Previous</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="pg-btn" href="<?= e(url('/accounts?page=' . ($page + 1) . ($search !== '' ? '&q=' . urlencode($search) : ''))) ?>">Next</a><?php endif; ?>
        </div>
    </div>
<?php endif; ?>
