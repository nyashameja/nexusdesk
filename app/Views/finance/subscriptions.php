<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<int,array<string,mixed>> $subscriptions */
/** @var string $status */
/** @var array<int,string> $statuses */
/** @var int $page, $pages, $total */
/** @var \ParagonHostOps\Services\Auth $auth */
$this->layout('layouts.app');

$badge = static fn (string $s): string => match ($s) {
    'paid', 'complimentary' => 'ok', 'due' => 'info', 'partial' => 'warn',
    'overdue', 'suspended' => 'danger', 'cancelled' => 'neutral', default => 'neutral',
};
$canManage = $auth->can('finance.manage');
?>
<div class="pg-page-head">
    <div>
        <div class="flex items-center gap-2 mb-1"><a class="pg-soft" href="<?= e(url('/finance')) ?>" style="font-size:12.5px">← Finance</a></div>
        <h1>Subscriptions</h1>
        <p><?= (int) $total ?> recurring subscription(s).</p>
    </div>
    <?php if ($canManage): ?>
        <a class="pg-btn primary" href="<?= e(url('/finance/subscriptions/create')) ?>">+ New subscription</a>
    <?php endif; ?>
</div>

<div class="pg-card mb-3">
    <div class="pg-card-body">
        <form method="get" action="<?= e(url('/finance/subscriptions')) ?>" class="flex gap-2" style="align-items:flex-end">
            <div class="pg-field" style="margin:0"><label>Payment status</label>
                <select class="pg-input" name="status"><option value="">All</option>
                    <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button class="pg-btn primary" type="submit">Filter</button>
            <?php if ($status !== ''): ?><a class="pg-btn ghost" href="<?= e(url('/finance/subscriptions')) ?>">Reset</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="pg-card">
    <div class="pg-table-wrap">
        <?php if (empty($subscriptions)): ?>
            <div class="pg-empty"><span class="ic">$</span><h3>No subscriptions</h3><p>Create subscriptions to track recurring revenue and renewals.</p></div>
        <?php else: ?>
            <table class="pg-table">
                <thead><tr><th>Client</th><th>Account</th><th>Description</th><th>Price</th><th>Cycle</th><th>Next billing</th><th>Status</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($subscriptions as $s): ?>
                    <tr>
                        <td><?= $s['client_id'] ? '<a href="' . e(url('/clients/' . (int) $s['client_id'])) . '">' . e($s['client_name'] ?: 'Client') . '</a>' : '<span class="pg-muted">—</span>' ?></td>
                        <td class="pg-soft"><?= e($s['account_domain'] ?? '—') ?></td>
                        <td class="pg-soft"><?= e($s['description'] ?? '—') ?></td>
                        <td><?= number_format((float) $s['price'], 2) ?></td>
                        <td class="pg-soft"><?= e(ucfirst((string) $s['billing_cycle'])) ?></td>
                        <td class="pg-soft"><?= !empty($s['next_billing_at']) ? e(date('d M Y', strtotime((string) $s['next_billing_at']))) : '—' ?></td>
                        <td><span class="pg-badge <?= $badge((string) $s['payment_status']) ?>"><?= e(ucfirst((string) $s['payment_status'])) ?></span></td>
                        <?php if ($canManage): ?><td class="text-right"><a class="pg-btn ghost" href="<?= e(url('/finance/subscriptions/' . (int) $s['id'] . '/edit')) ?>" style="padding:4px 10px">Edit</a></td><?php endif; ?>
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
            <?php $qs = $status !== '' ? '&status=' . urlencode($status) : ''; ?>
            <?php if ($page > 1): ?><a class="pg-btn" href="<?= e(url('/finance/subscriptions?page=' . ($page - 1) . $qs)) ?>">Previous</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="pg-btn" href="<?= e(url('/finance/subscriptions?page=' . ($page + 1) . $qs)) ?>">Next</a><?php endif; ?>
        </div>
    </div>
<?php endif; ?>
