<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<int,array<string,mixed>> $clients */
/** @var \ParagonHostOps\Repositories\ClientRepository $repo */
/** @var string $search, $status */
/** @var array<int,string> $statuses */
/** @var int $page, $pages, $total */
/** @var \ParagonHostOps\Services\Auth $auth */
$this->layout('layouts.app');

$badge = static fn (string $s): string => match ($s) {
    'active' => 'ok', 'prospect' => 'info', 'inactive' => 'neutral', 'archived' => 'warn', default => 'neutral',
};
?>
<div class="pg-page-head">
    <div>
        <h1>Clients</h1>
        <p><?= (int) $total ?> client<?= $total === 1 ? '' : 's' ?> in the CRM.</p>
    </div>
    <?php if ($auth->can('clients.manage')): ?>
        <a class="pg-btn primary" href="<?= e(url('/clients/create')) ?>">+ New client</a>
    <?php endif; ?>
</div>

<div class="pg-card mb-3">
    <div class="pg-card-body">
        <form method="get" action="<?= e(url('/clients')) ?>" class="flex gap-2 flex-wrap" style="align-items:flex-end">
            <div class="pg-field" style="margin:0;min-width:280px">
                <label>Search</label>
                <input class="pg-input" type="text" name="q" value="<?= e($search) ?>" placeholder="Company, name or email">
            </div>
            <div class="pg-field" style="margin:0">
                <label>Status</label>
                <select class="pg-input" name="status">
                    <option value="">All</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="pg-btn primary" type="submit">Filter</button>
            <?php if ($search !== '' || $status !== ''): ?><a class="pg-btn ghost" href="<?= e(url('/clients')) ?>">Reset</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="pg-card">
    <div class="pg-table-wrap">
        <?php if (empty($clients)): ?>
            <div class="pg-empty"><span class="ic">☰</span><h3>No clients</h3><p><?= $search !== '' || $status !== '' ? 'No clients match your filters.' : 'Create your first client to start linking hosting accounts.' ?></p></div>
        <?php else: ?>
            <table class="pg-table">
                <thead><tr><th>Client</th><th>Type</th><th>Email</th><th>Accounts</th><th>Domains</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($clients as $c): ?>
                    <tr>
                        <td><a href="<?= e(url('/clients/' . (int) $c['id'])) ?>"><strong><?= e($repo->displayName($c)) ?></strong></a></td>
                        <td class="pg-soft"><?= e(ucfirst((string) $c['client_type'])) ?></td>
                        <td class="pg-soft"><?= e($c['primary_email'] ?? '—') ?></td>
                        <td><?= (int) $c['account_count'] ?></td>
                        <td><?= (int) $c['domain_count'] ?></td>
                        <td><span class="pg-badge <?= $badge((string) $c['status']) ?>"><?= e(ucfirst((string) $c['status'])) ?></span></td>
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
            <?php if ($page > 1): ?><a class="pg-btn" href="<?= e(url('/clients?page=' . ($page - 1) . $qs)) ?>">Previous</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="pg-btn" href="<?= e(url('/clients?page=' . ($page + 1) . $qs)) ?>">Next</a><?php endif; ?>
        </div>
    </div>
<?php endif; ?>
