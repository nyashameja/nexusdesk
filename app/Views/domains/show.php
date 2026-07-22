<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<string,mixed> $domain */
/** @var \ParagonHostOps\Services\Auth $auth */
$this->layout('layouts.app');

$d = $domain;
$canFinance = $auth->can('finance.view');
$row = static fn (string $l, ?string $v): string => '<tr><td class="pg-soft" style="width:40%">' . e($l) . '</td><td>' . ($v !== null && $v !== '' ? e($v) : '<span class="pg-muted">—</span>') . '</td></tr>';
$clientName = trim((string) ($d['company_name'] ?? '') ?: trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')));
$days = $d['days_to_expiry'];
?>
<div class="pg-page-head">
    <div>
        <div class="flex items-center gap-2 mb-1"><a class="pg-soft" href="<?= e(url('/domains')) ?>" style="font-size:12.5px">← Domains</a></div>
        <h1><?= e($d['domain']) ?></h1>
        <p><span class="pg-badge neutral"><?= e(ucfirst(str_replace('_', ' ', (string) $d['status']))) ?></span></p>
    </div>
    <?php if ($auth->can('domains.manage')): ?>
        <a class="pg-btn" href="<?= e(url('/domains/' . (int) $d['id'] . '/edit')) ?>">Edit domain</a>
    <?php endif; ?>
</div>

<?php if ($days !== null && (int) $days < 0): ?>
    <div class="pg-alert error"><span><strong>Expired</strong> <?= abs((int) $days) ?> day(s) ago.</span></div>
<?php elseif ($days !== null && (int) $days <= 30): ?>
    <div class="pg-alert warn"><span>Expires in <strong><?= (int) $days ?> day(s)</strong>.</span></div>
<?php endif; ?>

<div class="pg-grid cols-2">
    <div class="pg-card">
        <div class="pg-card-head">Registration</div>
        <div class="pg-table-wrap"><table class="pg-table"><tbody>
            <?= $row('Registrar', $d['registrar'] ?? null) ?>
            <?= $row('Registered', !empty($d['registered_at']) ? date('d M Y', strtotime((string) $d['registered_at'])) : null) ?>
            <?= $row('Expires', !empty($d['expires_at']) ? date('d M Y', strtotime((string) $d['expires_at'])) : null) ?>
            <?= $row('Auto-renew', (int) ($d['auto_renew'] ?? 0) === 1 ? 'Enabled' : 'Disabled') ?>
            <?= $row('Nameserver 1', $d['nameserver1'] ?? null) ?>
            <?= $row('Nameserver 2', $d['nameserver2'] ?? null) ?>
        </tbody></table></div>
    </div>
    <div class="pg-card">
        <div class="pg-card-head">Links &amp; commercial</div>
        <div class="pg-table-wrap"><table class="pg-table"><tbody>
            <tr><td class="pg-soft" style="width:40%">Client</td><td><?= !empty($d['client_id']) ? '<a href="' . e(url('/clients/' . (int) $d['client_id'])) . '">' . e($clientName ?: 'Client') . '</a>' : '<span class="pg-muted">—</span>' ?></td></tr>
            <tr><td class="pg-soft">Hosting account</td><td><?= !empty($d['account_id']) ? '<a href="' . e(url('/accounts/' . (int) $d['account_id'])) . '">' . e($d['account_domain'] ?? $d['account_username']) . '</a>' : '<span class="pg-muted">—</span>' ?></td></tr>
            <?php if ($canFinance): ?>
                <?= $row('Renewal cost', $d['renewal_cost'] !== null ? number_format((float) $d['renewal_cost'], 2) : null) ?>
                <?= $row('Client price', $d['client_price'] !== null ? number_format((float) $d['client_price'], 2) : null) ?>
            <?php endif; ?>
        </tbody></table></div>
    </div>
</div>

<?php if (!empty($d['notes'])): ?>
    <div class="pg-card mt-3"><div class="pg-card-head">Notes</div><div class="pg-card-body" style="white-space:pre-wrap"><?= e($d['notes']) ?></div></div>
<?php endif; ?>
