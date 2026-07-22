<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<string,int> $summary */
/** @var array<int,array<string,mixed>> $loginFailures, $locked, $recentAudit */
$this->layout('layouts.app');
$s = $summary;
$stat = static function (string $label, int $value, string $color = ''): string {
    $style = $color !== '' && $value > 0 ? ' style="color:var(--pg-' . $color . ')"' : '';
    return '<div class="pg-stat"><div class="label">' . e($label) . '</div><div class="value"' . $style . '>' . $value . '</div></div>';
};
?>
<div class="pg-page-head">
    <div>
        <h1>Security Centre</h1>
        <p>Internal security overview from application and cached hosting data.</p>
    </div>
</div>

<div class="pg-grid cols-4 mb-3">
    <?= $stat('Login failures (24h)', $s['login_failures_24h'], 'warning') ?>
    <?= $stat('Locked app accounts', $s['locked_accounts'], 'danger') ?>
    <?= $stat('WHM auth failures', $s['whm_auth_failures'], 'danger') ?>
    <?= $stat('WHM permission failures', $s['whm_permission_failures'], 'warning') ?>
</div>
<div class="pg-grid cols-4 mb-3">
    <?= $stat('SSL issues', $s['ssl_issues'], 'danger') ?>
    <?= $stat('Sites offline', $s['sites_offline'], 'danger') ?>
    <?= $stat('High-disk accounts', $s['high_disk_accounts'], 'warning') ?>
    <?= $stat('Suspended accounts', $s['suspended_accounts'], 'warning') ?>
</div>

<div class="pg-grid cols-2 mb-3">
    <div class="pg-card">
        <div class="pg-card-head">Recent login failures</div>
        <div class="pg-table-wrap">
            <?php if (empty($loginFailures)): ?>
                <div class="pg-empty" style="padding:24px"><span class="ic">⚿</span><h3>No recent failures</h3><p>No failed logins recorded.</p></div>
            <?php else: ?>
                <table class="pg-table"><thead><tr><th>Email</th><th>IP</th><th>When</th></tr></thead><tbody>
                <?php foreach ($loginFailures as $f): ?>
                    <tr><td class="pg-soft"><?= e($f['email'] ?? '—') ?></td><td class="pg-soft"><?= e($f['ip_address'] ?? '—') ?></td><td class="pg-soft"><?= e(date('d M H:i', strtotime((string) $f['created_at'] . ' UTC'))) ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
    </div>

    <div class="pg-card">
        <div class="pg-card-head">Locked accounts</div>
        <div class="pg-table-wrap">
            <?php if (empty($locked)): ?>
                <div class="pg-empty" style="padding:24px"><span class="ic">✔</span><h3>None locked</h3><p>No application accounts are currently locked.</p></div>
            <?php else: ?>
                <table class="pg-table"><thead><tr><th>User</th><th>Email</th><th>Locked until</th></tr></thead><tbody>
                <?php foreach ($locked as $u): ?>
                    <tr><td><?= e($u['name']) ?></td><td class="pg-soft"><?= e($u['email']) ?></td><td class="pg-soft"><?= e(date('d M H:i', strtotime((string) $u['locked_until'] . ' UTC'))) ?> UTC</td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="pg-grid cols-2">
    <div class="pg-card">
        <div class="pg-card-head">Malware &amp; WAF</div>
        <div class="pg-card-body">
            <table class="pg-table"><tbody>
                <tr><td class="pg-soft" style="width:50%">Imunify360</td><td><span class="pg-badge neutral">Integration not configured</span></td></tr>
                <tr><td class="pg-soft">ModSecurity</td><td><span class="pg-badge neutral">Integration not configured</span></td></tr>
            </tbody></table>
            <p class="pg-muted mb-0" style="font-size:11.5px">Malware/WAF status is shown only when a real scanner API is integrated — no findings are fabricated.</p>
        </div>
    </div>

    <div class="pg-card">
        <div class="pg-card-head">Recent audit activity</div>
        <div class="pg-table-wrap">
            <?php if (empty($recentAudit)): ?>
                <div class="pg-empty" style="padding:24px"><span class="ic">❐</span><h3>No activity</h3><p>Audit entries appear here as actions occur.</p></div>
            <?php else: ?>
                <table class="pg-table"><thead><tr><th>Action</th><th>User</th><th>When</th></tr></thead><tbody>
                <?php foreach ($recentAudit as $a): ?>
                    <tr><td><span class="pg-badge neutral"><?= e($a['action']) ?></span> <span class="pg-soft" style="font-size:12px"><?= e($a['description'] ?? '') ?></span></td><td class="pg-soft"><?= e($a['user_name'] ?? 'System') ?></td><td class="pg-soft"><?= e(date('d M H:i', strtotime((string) $a['created_at'] . ' UTC'))) ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
    </div>
</div>
<p class="text-center mt-2"><a class="pg-btn ghost" href="<?= e(url('/audit-logs')) ?>">View full audit log</a></p>
