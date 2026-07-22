<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<int,array<string,mixed>> $certs */
/** @var array<string,int> $summary */
/** @var string $filter, $search */
/** @var int $page, $pages, $total */
$this->layout('layouts.app');

$band = static function (array $c): array {
    $status = (string) ($c['status'] ?? 'unknown');
    $days = $c['days_remaining'];
    if ($status === 'expired' || ($days !== null && (int) $days < 0)) { return ['danger', 'Expired']; }
    if (in_array($status, ['invalid', 'missing'], true)) { return ['danger', ucfirst($status)]; }
    if ($status === 'unknown' || $days === null) { return ['neutral', 'Unable to verify']; }
    $days = (int) $days;
    if ($days <= 14) { return ['danger', $days . 'd — critical']; }
    if ($days <= 30) { return ['warn', $days . 'd — expiring']; }
    if ($days <= 60) { return ['info', $days . 'd']; }
    return ['ok', 'Valid'];
};
$chip = static function (string $label, int $count, string $cls, string $filterVal, string $current): string {
    $active = $current === $filterVal ? 'primary' : '';
    return '<a class="pg-btn ' . $active . '" href="' . e(url('/ssl' . ($filterVal !== '' ? '?filter=' . $filterVal : ''))) . '">' . e($label) . ' <span class="pg-badge ' . $cls . '" style="margin-left:4px">' . $count . '</span></a>';
};
?>
<div class="pg-page-head">
    <div>
        <h1>SSL Centre</h1>
        <p>Read-only certificate monitoring across all hosting accounts. <?= (int) $summary['total'] ?> certificate(s) tracked.</p>
    </div>
</div>

<div class="pg-card mb-3">
    <div class="pg-card-body flex gap-2 flex-wrap">
        <?= $chip('All', $summary['total'], 'neutral', '', $filter) ?>
        <?= $chip('Valid', $summary['healthy'], 'ok', 'valid', $filter) ?>
        <?= $chip('≤ 30 days', $summary['d30'], 'warn', 'expiring', $filter) ?>
        <?= $chip('Expired', $summary['expired'], 'danger', 'expired', $filter) ?>
        <?= $chip('Needs attention', $summary['d30'] + $summary['d14'] + $summary['expired'] + $summary['issues'], 'danger', 'attention', $filter) ?>
    </div>
</div>

<div class="pg-card mb-3">
    <div class="pg-card-body">
        <form method="get" action="<?= e(url('/ssl')) ?>" class="flex gap-2">
            <?php if ($filter !== ''): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
            <input class="pg-input" name="q" value="<?= e($search) ?>" placeholder="Search by domain or account" style="max-width:340px">
            <button class="pg-btn primary" type="submit">Search</button>
            <?php if ($search !== ''): ?><a class="pg-btn ghost" href="<?= e(url('/ssl' . ($filter !== '' ? '?filter=' . $filter : ''))) ?>">Clear</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="pg-card">
    <div class="pg-table-wrap">
        <?php if (empty($certs)): ?>
            <div class="pg-empty"><span class="ic">⛨</span><h3>No certificates</h3><p><?= $search !== '' || $filter !== '' ? 'No certificates match the current view.' : 'SSL data appears here after a synchronisation. If it stays empty, SSL info may be unavailable with the current WHM permissions.' ?></p></div>
        <?php else: ?>
            <table class="pg-table">
                <thead><tr><th>Domain</th><th>Account</th><th>Issuer</th><th>Valid from</th><th>Expires</th><th>Days</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($certs as $c): [$cls, $label] = $band($c); ?>
                    <tr>
                        <td><strong><?= e($c['domain']) ?></strong>
                            <?php if (!empty($c['covered_hosts'])): ?><div class="pg-muted" style="font-size:11px"><?= e($c['covered_hosts']) ?></div><?php endif; ?>
                        </td>
                        <td class="pg-soft"><?php if (!empty($c['acct_id'])): ?><a href="<?= e(url('/accounts/' . (int) $c['acct_id'])) ?>"><?= e($c['account_username']) ?></a><?php else: ?>—<?php endif; ?></td>
                        <td class="pg-soft"><?= e($c['issuer'] ?? '—') ?></td>
                        <td class="pg-soft"><?= !empty($c['valid_from']) ? e(date('d M Y', strtotime((string) $c['valid_from']))) : '—' ?></td>
                        <td class="pg-soft"><?= !empty($c['valid_to']) ? e(date('d M Y', strtotime((string) $c['valid_to']))) : '—' ?></td>
                        <td class="pg-soft"><?= $c['days_remaining'] !== null ? (int) $c['days_remaining'] : '—' ?></td>
                        <td><span class="pg-badge <?= $cls ?>"><?= e($label) ?></span></td>
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
            <?php $qs = ($filter !== '' ? '&filter=' . urlencode($filter) : '') . ($search !== '' ? '&q=' . urlencode($search) : ''); ?>
            <?php if ($page > 1): ?><a class="pg-btn" href="<?= e(url('/ssl?page=' . ($page - 1) . $qs)) ?>">Previous</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="pg-btn" href="<?= e(url('/ssl?page=' . ($page + 1) . $qs)) ?>">Next</a><?php endif; ?>
        </div>
    </div>
<?php endif; ?>
