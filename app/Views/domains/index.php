<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<int,array<string,mixed>> $domains */
/** @var array<string,int> $counts */
/** @var string $search, $status */
/** @var array<int,string> $statuses */
/** @var int $page, $pages, $total */
/** @var \ParagonHostOps\Services\Auth $auth */
$this->layout('layouts.app');

/** Expiry badge from days remaining (90/60/30/14/7/expired thresholds). */
$expiryBadge = static function ($days): string {
    if ($days === null) { return '<span class="pg-muted">—</span>'; }
    $days = (int) $days;
    if ($days < 0)   { return '<span class="pg-badge danger">Expired ' . abs($days) . 'd ago</span>'; }
    if ($days <= 7)  { return '<span class="pg-badge danger">' . $days . 'd left</span>'; }
    if ($days <= 30) { return '<span class="pg-badge warn">' . $days . 'd left</span>'; }
    if ($days <= 90) { return '<span class="pg-badge info">' . $days . 'd left</span>'; }
    return '<span class="pg-soft">' . $days . 'd</span>';
};
$clientName = static fn (array $d): string => trim((string) ($d['company_name'] ?? '') ?: trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')));
?>
<div class="pg-page-head">
    <div>
        <h1>Domains</h1>
        <p><?= (int) $total ?> domain<?= $total === 1 ? '' : 's' ?> in the local registry.</p>
    </div>
    <?php if ($auth->can('domains.manage')): ?>
        <div class="flex gap-2 flex-wrap">
            <form method="post" action="<?= e(url('/domains/import-from-accounts')) ?>" style="margin:0"><?= csrf_field() ?><button class="pg-btn" type="submit" title="Add any hosting account domain that isn't tracked yet, then check its expiry">⇩ Import from hosting accounts</button></form>
            <form method="post" action="<?= e(url('/domains/check-all')) ?>" style="margin:0"><?= csrf_field() ?><button class="pg-btn" type="submit" title="Look up expiry for all domains via RDAP/WHOIS">↻ Check all expiry</button></form>
            <a class="pg-btn primary" href="<?= e(url('/domains/create')) ?>">+ Add domain</a>
        </div>
    <?php endif; ?>
</div>

<div class="pg-grid cols-4 mb-3">
    <div class="pg-stat"><div class="label">Total</div><div class="value"><?= (int) $total ?></div></div>
    <div class="pg-stat"><div class="label">Expiring ≤ 30 days</div><div class="value" style="color:var(--pg-warning)"><?= (int) ($counts['_within30'] ?? 0) ?></div></div>
    <div class="pg-stat"><div class="label">Expired</div><div class="value" style="color:var(--pg-danger)"><?= (int) ($counts['_expired'] ?? 0) ?></div></div>
    <div class="pg-stat"><div class="label">Active</div><div class="value" style="color:var(--pg-success)"><?= (int) ($counts['active'] ?? 0) ?></div></div>
</div>

<div class="pg-card mb-3">
    <div class="pg-card-body">
        <form method="get" action="<?= e(url('/domains')) ?>" class="flex gap-2 flex-wrap" style="align-items:flex-end">
            <div class="pg-field" style="margin:0;min-width:260px"><label>Search</label><input class="pg-input" name="q" value="<?= e($search) ?>" placeholder="Domain or registrar"></div>
            <div class="pg-field" style="margin:0"><label>Status</label>
                <select class="pg-input" name="status"><option value="">All</option>
                    <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button class="pg-btn primary" type="submit">Filter</button>
            <?php if ($search !== '' || $status !== ''): ?><a class="pg-btn ghost" href="<?= e(url('/domains')) ?>">Reset</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="pg-card">
    <div class="pg-table-wrap">
        <?php if (empty($domains)): ?>
            <div class="pg-empty"><span class="ic">◈</span><h3>No domains</h3><p>Add domains to track registrations, expiry and renewals.</p></div>
        <?php else: ?>
            <table class="pg-table">
                <thead><tr><th>Domain</th><th>Client</th><th>Registrar</th><th>Status</th><th>Expiry</th><th>Alert</th></tr></thead>
                <tbody>
                <?php foreach ($domains as $d): ?>
                    <tr>
                        <td><a href="<?= e(url('/domains/' . (int) $d['id'])) ?>"><strong><?= e($d['domain']) ?></strong></a></td>
                        <td class="pg-soft"><?= e($clientName($d) ?: '—') ?></td>
                        <td class="pg-soft"><?= e($d['registrar'] ?? '—') ?></td>
                        <td><span class="pg-badge neutral"><?= e(ucfirst(str_replace('_', ' ', (string) $d['status']))) ?></span></td>
                        <td class="pg-soft"><?= !empty($d['expires_at']) ? e(date('d M Y', strtotime((string) $d['expires_at']))) : '—' ?></td>
                        <td><?= $expiryBadge($d['days_to_expiry']) ?></td>
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
            <?php if ($page > 1): ?><a class="pg-btn" href="<?= e(url('/domains?page=' . ($page - 1) . $qs)) ?>">Previous</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="pg-btn" href="<?= e(url('/domains?page=' . ($page + 1) . $qs)) ?>">Next</a><?php endif; ?>
        </div>
    </div>
<?php endif; ?>
