<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<string,mixed> $account */
/** @var \ParagonHostOps\Services\Auth $auth */
$this->layout('layouts.app');

$a = $account;
$fmtMb = static function ($mb): string {
    $mb = (float) $mb;
    return $mb >= 1024 ? number_format($mb / 1024, 2) . ' GB' : number_format($mb) . ' MB';
};
$pct = static function ($used, $limit): int {
    $used = (float) $used; $limit = (float) $limit;
    return $limit > 0 ? (int) min(100, round(($used / $limit) * 100)) : 0;
};
$row = static fn (string $label, ?string $value): string =>
    '<tr><td class="pg-soft" style="width:42%">' . e($label) . '</td><td>' . ($value !== null && $value !== '' ? e($value) : '<span class="pg-muted">—</span>') . '</td></tr>';

$diskP = $pct($a['disk_used_mb'] ?? 0, $a['disk_limit_mb'] ?? 0);
$bwP   = $pct($a['bandwidth_used_mb'] ?? 0, $a['bandwidth_limit_mb'] ?? 0);
$clientName = trim((string) ($a['company_name'] ?? '') ?: trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')));
$sub = $a['subscription'] ?? null;
?>
<div class="pg-page-head">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <a class="pg-soft" href="<?= e(url('/accounts')) ?>" style="font-size:12.5px">← Accounts</a>
        </div>
        <h1><?= e($a['domain'] ?? '') ?></h1>
        <p><?= e($a['username'] ?? '') ?> · <?= e($a['server_hostname'] ?? $a['server_name'] ?? 'server') ?>
            <?php if ((int) ($a['suspended'] ?? 0) === 1): ?>
                · <span class="pg-badge danger">Suspended</span>
            <?php else: ?>
                · <span class="pg-badge ok">Active</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($auth->can('sync.run')): ?>
            <form action="<?= e(url('/accounts/' . (int) $a['id'] . '/refresh')) ?>" method="post" style="margin:0">
                <?= csrf_field() ?>
                <button class="pg-btn primary" type="submit">⟳ Refresh account data</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ((int) ($a['suspended'] ?? 0) === 1 && !empty($a['suspend_reason'])): ?>
    <div class="pg-alert warn"><span><strong>Suspended:</strong> <?= e($a['suspend_reason']) ?></span></div>
<?php endif; ?>

<div class="pg-grid cols-2 mb-3">
    <!-- Account overview -->
    <div class="pg-card">
        <div class="pg-card-head">Account overview</div>
        <div class="pg-table-wrap">
            <table class="pg-table">
                <tbody>
                    <?= $row('Primary domain', $a['domain'] ?? null) ?>
                    <?= $row('cPanel username', $a['username'] ?? null) ?>
                    <?= $row('Owner / reseller', $a['owner'] ?? null) ?>
                    <?= $row('Package', $a['package'] ?? null) ?>
                    <?= $row('IP address', $a['ip_address'] ?? null) ?>
                    <?= $row('Contact email', $a['email'] ?? null) ?>
                    <?= $row('Theme', $a['theme'] ?? null) ?>
                    <?= $row('Locale', $a['locale'] ?? null) ?>
                    <?= $row('Created', !empty($a['whm_created_at']) ? date('d M Y', strtotime((string) $a['whm_created_at'])) : null) ?>
                    <?= $row('Last synced', !empty($a['last_synced_at']) ? date('d M Y H:i', strtotime((string) $a['last_synced_at'] . ' UTC')) . ' UTC' : null) ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Resource usage -->
    <div class="pg-card">
        <div class="pg-card-head">Resource usage</div>
        <div class="pg-card-body">
            <div class="mb-3">
                <div class="flex justify-between mb-1"><span class="pg-soft" style="font-size:12.5px">Disk</span><span style="font-size:12.5px"><?= $fmtMb($a['disk_used_mb'] ?? 0) ?> / <?= (float) ($a['disk_limit_mb'] ?? 0) > 0 ? $fmtMb($a['disk_limit_mb']) : 'Unlimited' ?> (<?= $diskP ?>%)</span></div>
                <div class="pg-progress" style="height:9px"><span class="<?= $diskP >= 90 ? 'danger' : ($diskP >= 80 ? 'warn' : '') ?>" style="width:<?= $diskP ?>%"></span></div>
            </div>
            <div class="mb-3">
                <div class="flex justify-between mb-1"><span class="pg-soft" style="font-size:12.5px">Bandwidth</span><span style="font-size:12.5px"><?= $fmtMb($a['bandwidth_used_mb'] ?? 0) ?> / <?= (float) ($a['bandwidth_limit_mb'] ?? 0) > 0 ? $fmtMb($a['bandwidth_limit_mb']) : 'Unlimited' ?> (<?= $bwP ?>%)</span></div>
                <div class="pg-progress" style="height:9px"><span class="<?= $bwP >= 90 ? 'danger' : ($bwP >= 80 ? 'warn' : '') ?>" style="width:<?= $bwP ?>%"></span></div>
            </div>
            <table class="pg-table">
                <tbody>
                    <?= $row('Email accounts', isset($a['email_accounts']) && $a['email_accounts'] !== null ? (string) $a['email_accounts'] : null) ?>
                    <?= $row('Usage captured', !empty($a['captured_at']) ? date('d M Y H:i', strtotime((string) $a['captured_at'] . ' UTC')) . ' UTC' : null) ?>
                </tbody>
            </table>
            <p class="pg-muted mt-1 mb-0" style="font-size:11.5px">Deeper mailbox data requires the cPanel API privilege (added in a future version).</p>
        </div>
    </div>
</div>

<div class="pg-grid cols-2">
    <!-- SSL -->
    <div class="pg-card">
        <div class="pg-card-head">SSL certificates</div>
        <div class="pg-table-wrap">
            <?php if (empty($a['ssl_certs'])): ?>
                <div class="pg-empty" style="padding:28px"><span class="ic">⛨</span><h3>No certificate data</h3><p>No SSL information cached for this account, or unavailable with current WHM permissions.</p></div>
            <?php else: ?>
                <table class="pg-table">
                    <thead><tr><th>Status</th><th>Issuer</th><th>Expires</th><th>Days</th></tr></thead>
                    <tbody>
                    <?php foreach ($a['ssl_certs'] as $c):
                        $s = (string) ($c['status'] ?? 'unknown');
                        $sc = match ($s) { 'valid' => 'ok', 'expiring' => 'warn', 'expired', 'invalid', 'missing' => 'danger', default => 'neutral' };
                    ?>
                        <tr>
                            <td><span class="pg-badge <?= $sc ?>"><?= e(ucfirst($s)) ?></span></td>
                            <td class="pg-soft"><?= e($c['issuer'] ?? '—') ?></td>
                            <td class="pg-soft"><?= !empty($c['valid_to']) ? e(date('d M Y', strtotime((string) $c['valid_to']))) : '—' ?></td>
                            <td class="pg-soft"><?= $c['days_remaining'] !== null ? (int) $c['days_remaining'] : '—' ?></td>
                        </tr>
                        <?php if (!empty($c['covered_hosts'])): ?>
                            <tr><td colspan="4" class="pg-muted" style="font-size:11.5px;padding-top:0">Covers: <?= e($c['covered_hosts']) ?></td></tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Internal business data -->
    <div class="pg-card">
        <div class="pg-card-head">Internal business data</div>
        <div class="pg-table-wrap">
            <table class="pg-table">
                <tbody>
                    <tr>
                        <td class="pg-soft" style="width:42%">Linked client</td>
                        <td>
                            <?php if (!empty($a['client_id_linked'])): ?>
                                <a href="<?= e(url('/clients/' . (int) $a['client_id_linked'])) ?>"><?= e($clientName ?: 'Client #' . (int) $a['client_id_linked']) ?></a>
                            <?php else: ?>
                                <span class="pg-badge neutral">Not linked</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($auth->can('finance.view')): ?>
                        <?= $row('Hosting price', $sub && $sub['price'] !== null ? number_format((float) $sub['price'], 2) : null) ?>
                        <?= $row('Billing frequency', $sub['billing_cycle'] ?? null) ?>
                        <?= $row('Renewal date', !empty($sub['next_billing_at']) ? date('d M Y', strtotime((string) $sub['next_billing_at'])) : null) ?>
                        <?= $row('Payment status', $sub['payment_status'] ?? null) ?>
                        <?= $row('Outstanding', $sub && $sub['outstanding'] !== null ? number_format((float) $sub['outstanding'], 2) : null) ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="pg-muted" style="font-size:12px">Financial details are restricted to finance and administrator roles.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <p class="pg-muted mb-0" style="font-size:11.5px;padding:0 14px 14px">Client linking, pricing and notes are managed in the Clients module (Phase 4).</p>
        </div>
    </div>
</div>
