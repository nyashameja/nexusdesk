<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<string,int|float> $summary */
/** @var array{valid:int,expiring:int,issues:int} $ssl */
/** @var int $packageCount */
/** @var string|null $lastSync */
/** @var bool $whmConfigured */
/** @var array<int,array<string,mixed>> $attention */
/** @var array<string,mixed> $charts */
/** @var array<string,mixed>|null $domainExpiry */
/** @var \ParagonHostOps\Services\Auth $auth */
/** @var array{whmConfigured:bool, lastSync:?string, server:?string} $shell */
$this->layout('layouts.app');

$fmtGb   = static fn (float $mb): string => number_format($mb / 1024, 1) . ' GB';
$hasData = ($summary['total'] ?? 0) > 0;

$pct = static function ($used, $limit): int {
    $used = (float) $used; $limit = (float) $limit;
    return $limit > 0 ? (int) min(100, round(($used / $limit) * 100)) : 0;
};

$hour     = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$firstName = trim(explode(' ', $auth->name())[0] ?? '');

/** Attention rows are rendered twice (table + cards) — build the badges once. */
$issuesFor = static function (array $a) use ($pct): array {
    $dp = $pct($a['disk_used'] ?? 0, $a['disk_limit'] ?? 0);
    $bp = $pct($a['bw_used'] ?? 0, $a['bw_limit'] ?? 0);
    $issues = [];
    if ((int) ($a['suspended'] ?? 0) === 1) {
        $issues[] = '<span class="pg-badge danger">⛔ Suspended</span>';
    }
    if ($dp >= 80) {
        $issues[] = '<span class="pg-badge ' . ($dp >= 95 ? 'danger' : 'warn') . '">Disk ' . $dp . '%</span>';
    }
    if ($bp >= 80) {
        $issues[] = '<span class="pg-badge ' . ($bp >= 95 ? 'danger' : 'warn') . '">Bandwidth ' . $bp . '%</span>';
    }
    $sslDays = $a['ssl_days'] ?? null;
    if (in_array($a['ssl_status'] ?? '', ['expired', 'invalid', 'missing'], true)) {
        $issues[] = '<span class="pg-badge danger">SSL ' . e((string) $a['ssl_status']) . '</span>';
    } elseif (($a['ssl_status'] ?? '') === 'expiring' || ($sslDays !== null && $sslDays <= 30)) {
        $issues[] = '<span class="pg-badge warn">SSL ' . ($sslDays !== null ? (int) $sslDays . 'd left' : 'expiring') . '</span>';
    }
    return $issues;
};

$expiryBadge = static function ($days): string {
    if ($days === null) { return '<span class="pg-muted">—</span>'; }
    $days = (int) $days;
    if ($days < 0)   { return '<span class="pg-badge danger">Expired ' . abs($days) . 'd ago</span>'; }
    if ($days <= 7)  { return '<span class="pg-badge danger">' . $days . 'd left</span>'; }
    if ($days <= 30) { return '<span class="pg-badge warn">' . $days . 'd left</span>'; }
    return '<span class="pg-badge info">' . $days . 'd left</span>';
};

$highUsage   = (int) ($summary['high_disk'] ?? 0) + (int) ($summary['high_bw'] ?? 0);
$attentionN  = count($attention);
$domainsSoon = (int) ($domainExpiry['within30'] ?? 0) + (int) ($domainExpiry['expired'] ?? 0);
?>
<div class="pg-page-head pg-greeting">
    <div>
        <h1><?= $greeting ?><?= $firstName !== '' ? ', ' . e($firstName) : '' ?></h1>
        <div class="pg-meta-row">
            <?php if ($shell['server'] !== null): ?>
                <span><span class="pg-muted">Server</span> <strong><?= e($shell['server']) ?></strong></span>
            <?php endif; ?>
            <?php if ($whmConfigured): ?>
                <span class="pg-badge ok"><span class="pg-dot ok" aria-hidden="true"></span> WHM connected</span>
            <?php else: ?>
                <span class="pg-badge warn"><span class="pg-dot warn" aria-hidden="true"></span> WHM not configured</span>
            <?php endif; ?>
            <span class="pg-muted">Last synchronised <?= e(time_ago($lastSync)) ?></span>
        </div>
    </div>
    <?php if ($auth->can('sync.run')): ?>
        <form method="post" action="<?= e(url('/sync/run')) ?>" style="margin:0">
            <?= csrf_field() ?>
            <button class="pg-btn primary" type="submit">⟳ Synchronise now</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!$hasData): ?>
    <div class="pg-alert info">
        <span class="pg-alert-ic" aria-hidden="true">ⓘ</span>
        <span>
            No cached hosting data yet. Configure WHM credentials and run a
            <a href="<?= e(url('/sync')) ?>">synchronisation</a> — live figures and charts will appear here.
        </span>
    </div>
<?php endif; ?>

<!-- KPI row -->
<div class="pg-grid auto mb-3">
    <div class="pg-stat">
        <div class="flex justify-between items-center"><span class="label">Active accounts</span><span class="ic teal" aria-hidden="true">▤</span></div>
        <div class="value"><?= (int) ($summary['active'] ?? 0) ?></div>
        <div class="sub"><?= (int) ($summary['total'] ?? 0) ?> total · <?= (int) ($summary['suspended'] ?? 0) ?> suspended</div>
    </div>
    <div class="pg-stat">
        <div class="flex justify-between items-center"><span class="label">Needs attention</span><span class="ic <?= $attentionN > 0 ? 'red' : 'green' ?>" aria-hidden="true"><?= $attentionN > 0 ? '▲' : '✓' ?></span></div>
        <div class="value"><?= $attentionN ?></div>
        <div class="sub"><?= $attentionN > 0 ? 'Accounts with open issues' : 'All monitored accounts healthy' ?></div>
    </div>
    <div class="pg-stat">
        <div class="flex justify-between items-center"><span class="label">Domains expiring</span><span class="ic <?= $domainsSoon > 0 ? 'amber' : 'green' ?>" aria-hidden="true">◈</span></div>
        <div class="value"><?= $domainExpiry === null ? '—' : $domainsSoon ?></div>
        <div class="sub"><?= $domainExpiry === null ? 'Requires domain access' : 'Within 30 days or already expired' ?></div>
    </div>
    <div class="pg-stat">
        <div class="flex justify-between items-center"><span class="label">SSL expiring</span><span class="ic <?= ((int) $ssl['expiring'] + (int) $ssl['issues']) > 0 ? 'amber' : 'green' ?>" aria-hidden="true">⛨</span></div>
        <div class="value"><?= (int) $ssl['expiring'] ?></div>
        <div class="sub"><?= (int) $ssl['valid'] ?> valid · <?= (int) $ssl['issues'] ?> with issues</div>
    </div>
    <div class="pg-stat">
        <div class="flex justify-between items-center"><span class="label">Disk utilisation</span><span class="ic blue" aria-hidden="true">◔</span></div>
        <div class="value"><?= number_format((float) ($summary['disk_percent'] ?? 0), 1) ?>%</div>
        <div class="sub"><?= $fmtGb((float) ($summary['disk_used_mb'] ?? 0)) ?> of <?= $fmtGb((float) ($summary['disk_limit_mb'] ?? 0)) ?></div>
    </div>
    <div class="pg-stat">
        <div class="flex justify-between items-center"><span class="label">Accounts over 80%</span><span class="ic <?= $highUsage > 0 ? 'red' : 'green' ?>" aria-hidden="true">⇅</span></div>
        <div class="value"><?= $highUsage ?></div>
        <div class="sub"><?= (int) ($summary['high_disk'] ?? 0) ?> disk · <?= (int) ($summary['high_bw'] ?? 0) ?> bandwidth</div>
    </div>
</div>

<!-- Needs attention — the most operationally important block -->
<div class="pg-card mb-3 pg-responsive-table">
    <div class="pg-card-head">
        <span>Needs attention <?php if ($attentionN > 0): ?><span class="pg-badge danger"><?= $attentionN ?></span><?php endif; ?></span>
        <a class="pg-btn ghost sm" href="<?= e(url('/accounts?ssl=attention')) ?>">View all accounts</a>
    </div>

    <?php if ($attention === []): ?>
        <div class="pg-empty">
            <span class="ic" aria-hidden="true">✓</span>
            <h3><?= $hasData ? 'No critical alerts' : 'Nothing to show yet' ?></h3>
            <p><?= $hasData
                ? 'All monitored hosting accounts are currently healthy.'
                : 'Attention items appear after the first synchronisation.' ?></p>
        </div>
    <?php else: ?>
        <div class="pg-table-wrap">
            <table class="pg-table">
                <thead><tr><th scope="col">Account</th><th scope="col">Client</th><th scope="col">Issues</th><th scope="col">Action</th></tr></thead>
                <tbody>
                <?php foreach ($attention as $a): $issues = $issuesFor($a); ?>
                    <tr>
                        <td><a href="<?= e(url('/accounts/' . (int) $a['id'])) ?>"><strong><?= e($a['domain']) ?></strong></a></td>
                        <td class="pg-soft"><?= e($a['client_name'] ?? $a['username'] ?? '—') ?></td>
                        <td><div class="flex flex-wrap gap-2"><?= $issues ? implode(' ', $issues) : '<span class="pg-soft">—</span>' ?></div></td>
                        <td><a class="pg-btn sm" href="<?= e(url('/accounts/' . (int) $a['id'])) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile: same rows as structured cards rather than a squeezed table -->
        <div class="pg-records">
            <?php foreach ($attention as $a): $issues = $issuesFor($a); ?>
                <article class="pg-record">
                    <div class="pg-record-head">
                        <span class="pg-record-title"><?= e($a['domain']) ?></span>
                    </div>
                    <div class="pg-record-grid">
                        <div class="pg-record-field">
                            <span class="k">Client</span>
                            <span class="v"><?= e($a['client_name'] ?? $a['username'] ?? '—') ?></span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-2"><?= $issues ? implode(' ', $issues) : '<span class="pg-soft">—</span>' ?></div>
                    <div class="pg-record-actions">
                        <a class="pg-btn sm" href="<?= e(url('/accounts/' . (int) $a['id'])) ?>">Open account</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Resource usage -->
<div class="pg-grid cols-2 mb-3">
    <div class="pg-card">
        <div class="pg-card-head">Disk usage by account</div>
        <div class="pg-card-body"><div class="pg-chart" style="height:250px"><canvas id="chartDisk"></canvas></div></div>
    </div>
    <div class="pg-card">
        <div class="pg-card-head">Bandwidth usage by account</div>
        <div class="pg-card-body"><div class="pg-chart" style="height:250px"><canvas id="chartBandwidth"></canvas></div></div>
    </div>
</div>

<div class="pg-grid cols-3 mb-3">
    <div class="pg-card">
        <div class="pg-card-head">Accounts by package</div>
        <div class="pg-card-body"><div class="pg-chart" style="height:220px"><canvas id="chartPackage"></canvas></div></div>
    </div>
    <div class="pg-card">
        <div class="pg-card-head">Active vs suspended</div>
        <div class="pg-card-body"><div class="pg-chart" style="height:220px"><canvas id="chartStatus"></canvas></div></div>
    </div>
    <div class="pg-card">
        <div class="pg-card-head">SSL status distribution</div>
        <div class="pg-card-body"><div class="pg-chart" style="height:220px"><canvas id="chartSsl"></canvas></div></div>
    </div>
</div>

<div class="pg-grid cols-2 mb-3">
    <div class="pg-card">
        <div class="pg-card-head">Accounts created over time</div>
        <div class="pg-card-body"><div class="pg-chart" style="height:240px"><canvas id="chartCreated"></canvas></div></div>
    </div>

    <!-- Server status: only states the application can actually verify -->
    <div class="pg-card">
        <div class="pg-card-head">Platform status</div>
        <div class="pg-card-body">
            <ul class="pg-status-list">
                <li>
                    <span class="name">WHM connection</span>
                    <?= $whmConfigured
                        ? '<span class="pg-badge ok"><span class="pg-dot ok" aria-hidden="true"></span> Configured</span>'
                        : '<span class="pg-badge warn"><span class="pg-dot warn" aria-hidden="true"></span> Not configured</span>' ?>
                </li>
                <li>
                    <span class="name">Cached hosting data</span>
                    <?= $hasData
                        ? '<span class="pg-badge ok">' . (int) $summary['total'] . ' accounts</span>'
                        : '<span class="pg-badge neutral">Empty</span>' ?>
                </li>
                <li>
                    <span class="name">Last synchronisation</span>
                    <span class="pg-badge <?= $lastSync ? 'ok' : 'neutral' ?>"><?= e(time_ago($lastSync)) ?></span>
                </li>
                <li>
                    <span class="name">Hosting packages</span>
                    <span class="pg-badge neutral"><?= (int) $packageCount ?> defined</span>
                </li>
                <li>
                    <span class="name">SSL coverage</span>
                    <span class="pg-badge <?= (int) $ssl['issues'] > 0 ? 'warn' : 'ok' ?>">
                        <?= (int) $ssl['valid'] ?> valid · <?= (int) $ssl['issues'] ?> issues
                    </span>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php if (!empty($domainExpiry) && (($domainExpiry['within60'] ?? 0) > 0 || ($domainExpiry['expired'] ?? 0) > 0)): ?>
    <div class="pg-card pg-responsive-table">
        <div class="pg-card-head">
            <span>Domains expiring soon</span>
            <a class="pg-btn ghost sm" href="<?= e(url('/domains?status=expiring')) ?>">All domains</a>
        </div>
        <div class="pg-card-body" style="padding-bottom:0">
            <div class="flex gap-2 flex-wrap">
                <span class="pg-badge danger">Expired: <?= (int) $domainExpiry['expired'] ?></span>
                <span class="pg-badge warn">≤ 30 days: <?= (int) $domainExpiry['within30'] ?></span>
                <span class="pg-badge info">≤ 60 days: <?= (int) $domainExpiry['within60'] ?></span>
            </div>
        </div>
        <div class="pg-table-wrap">
            <table class="pg-table">
                <thead><tr><th scope="col">Domain</th><th scope="col">Expires</th><th scope="col">Remaining</th></tr></thead>
                <tbody>
                <?php foreach ($domainExpiry['soon'] as $d): ?>
                    <tr>
                        <td><a href="<?= e(url('/domains/' . (int) $d['id'])) ?>"><strong><?= e($d['domain']) ?></strong></a></td>
                        <td class="pg-soft"><?= !empty($d['expires_at']) ? e(date('d M Y', strtotime((string) $d['expires_at']))) : '—' ?></td>
                        <td><?= $expiryBadge($d['days_to_expiry']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="pg-records">
            <?php foreach ($domainExpiry['soon'] as $d): ?>
                <article class="pg-record">
                    <div class="pg-record-head">
                        <span class="pg-record-title"><?= e($d['domain']) ?></span>
                        <?= $expiryBadge($d['days_to_expiry']) ?>
                    </div>
                    <div class="pg-record-grid">
                        <div class="pg-record-field">
                            <span class="k">Expires</span>
                            <span class="v"><?= !empty($d['expires_at']) ? e(date('d M Y', strtotime((string) $d['expires_at']))) : '—' ?></span>
                        </div>
                    </div>
                    <div class="pg-record-actions">
                        <a class="pg-btn sm" href="<?= e(url('/domains/' . (int) $d['id'])) ?>">Open domain</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<script type="application/json" id="pg-dashboard-data"><?= json_encode($charts, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
