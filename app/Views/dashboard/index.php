<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<string,int|float> $summary */
/** @var array{valid:int,expiring:int,issues:int} $ssl */
/** @var int $packageCount */
/** @var string|null $lastSync */
/** @var bool $whmConfigured */
/** @var array<int,array<string,mixed>> $attention */
/** @var array<string,mixed> $charts */
$this->layout('layouts.app');

$fmtGb = static fn (float $mb): string => number_format($mb / 1024, 1) . ' GB';
$hasData = ($summary['total'] ?? 0) > 0;

$pct = static function ($used, $limit): int {
    $used = (float) $used; $limit = (float) $limit;
    return $limit > 0 ? (int) min(100, round(($used / $limit) * 100)) : 0;
};
?>
<div class="pg-page-head">
    <div>
        <h1>Overview</h1>
        <p>Executive summary of hosting operations. Figures reflect the last synchronisation.</p>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($whmConfigured): ?>
            <span class="pg-badge ok"><span class="pg-dot ok"></span> WHM configured</span>
        <?php else: ?>
            <span class="pg-badge warn"><span class="pg-dot warn"></span> WHM not configured</span>
        <?php endif; ?>
        <span class="pg-badge neutral">Last sync:
            <?= $lastSync ? e(date('d M Y H:i', strtotime($lastSync . ' UTC'))) : 'never' ?>
        </span>
    </div>
</div>

<?php if (!$hasData): ?>
    <div class="pg-card mb-3">
        <div class="pg-card-body">
            <div class="pg-alert info mb-0">
                <span>
                    No cached hosting data yet. Configure WHM credentials and run a
                    <a href="<?= e(url('/sync')) ?>">synchronisation</a> — live figures and charts will appear here.
                </span>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="pg-grid cols-4 mb-3">
    <div class="pg-stat">
        <div class="flex justify-between items-center"><span class="label">Total accounts</span><span class="ic blue">▤</span></div>
        <div class="value"><?= (int) $summary['total'] ?></div>
        <div class="sub"><?= (int) $summary['active'] ?> active · <?= (int) $summary['suspended'] ?> suspended</div>
    </div>
    <div class="pg-stat">
        <div class="flex justify-between items-center"><span class="label">Hosting packages</span><span class="ic teal">◫</span></div>
        <div class="value"><?= (int) $packageCount ?></div>
        <div class="sub">Defined on server</div>
    </div>
    <div class="pg-stat">
        <div class="flex justify-between items-center"><span class="label">Disk utilisation</span><span class="ic amber">◔</span></div>
        <div class="value"><?= number_format((float) $summary['disk_percent'], 1) ?>%</div>
        <div class="sub"><?= $fmtGb((float) $summary['disk_used_mb']) ?> of <?= $fmtGb((float) $summary['disk_limit_mb']) ?></div>
    </div>
    <div class="pg-stat">
        <div class="flex justify-between items-center"><span class="label">Bandwidth used</span><span class="ic blue">⇅</span></div>
        <div class="value"><?= $fmtGb((float) $summary['bw_used_mb']) ?></div>
        <div class="sub">Across all accounts</div>
    </div>
</div>

<div class="pg-grid cols-4 mb-3">
    <div class="pg-stat">
        <div class="flex justify-between items-center"><span class="label">SSL valid</span><span class="ic green">⛨</span></div>
        <div class="value"><?= (int) $ssl['valid'] ?></div>
        <div class="sub">More than 30 days remaining</div>
    </div>
    <div class="pg-stat">
        <div class="flex justify-between items-center"><span class="label">SSL expiring soon</span><span class="ic amber">⛨</span></div>
        <div class="value"><?= (int) $ssl['expiring'] ?></div>
        <div class="sub">Within 30 days</div>
    </div>
    <div class="pg-stat">
        <div class="flex justify-between items-center"><span class="label">SSL issues</span><span class="ic red">⛨</span></div>
        <div class="value"><?= (int) $ssl['issues'] ?></div>
        <div class="sub">Expired / invalid / missing</div>
    </div>
    <div class="pg-stat">
        <div class="flex justify-between items-center"><span class="label">Accounts &gt; 80% usage</span><span class="ic red">▲</span></div>
        <div class="value"><?= (int) $summary['high_disk'] + (int) $summary['high_bw'] ?></div>
        <div class="sub"><?= (int) $summary['high_disk'] ?> disk · <?= (int) $summary['high_bw'] ?> bandwidth</div>
    </div>
</div>

<!-- Charts -->
<div class="pg-grid cols-2 mb-3">
    <div class="pg-card">
        <div class="pg-card-head">Disk usage by account</div>
        <div class="pg-card-body"><div style="height:240px"><canvas id="chartDisk"></canvas></div></div>
    </div>
    <div class="pg-card">
        <div class="pg-card-head">Bandwidth usage by account</div>
        <div class="pg-card-body"><div style="height:240px"><canvas id="chartBandwidth"></canvas></div></div>
    </div>
</div>

<div class="pg-grid cols-3 mb-3">
    <div class="pg-card">
        <div class="pg-card-head">Accounts by package</div>
        <div class="pg-card-body"><div style="height:220px"><canvas id="chartPackage"></canvas></div></div>
    </div>
    <div class="pg-card">
        <div class="pg-card-head">Active vs suspended</div>
        <div class="pg-card-body"><div style="height:220px"><canvas id="chartStatus"></canvas></div></div>
    </div>
    <div class="pg-card">
        <div class="pg-card-head">SSL status distribution</div>
        <div class="pg-card-body"><div style="height:220px"><canvas id="chartSsl"></canvas></div></div>
    </div>
</div>

<div class="pg-grid cols-2">
    <div class="pg-card">
        <div class="pg-card-head">Accounts created over time</div>
        <div class="pg-card-body"><div style="height:230px"><canvas id="chartCreated"></canvas></div></div>
    </div>

    <div class="pg-card">
        <div class="pg-card-head flex justify-between items-center">
            <span>Accounts requiring attention</span>
            <a class="pg-btn ghost" href="<?= e(url('/accounts?ssl=attention')) ?>" style="padding:4px 10px">View all</a>
        </div>
        <div class="pg-table-wrap">
            <?php if (empty($attention)): ?>
                <div class="pg-empty"><span class="ic">◎</span><h3><?= $hasData ? 'All clear' : 'Nothing to show yet' ?></h3><p><?= $hasData ? 'No accounts currently require attention.' : 'Attention items appear after the first synchronisation.' ?></p></div>
            <?php else: ?>
                <table class="pg-table">
                    <thead><tr><th>Domain</th><th>Issue</th></tr></thead>
                    <tbody>
                    <?php foreach ($attention as $a):
                        $dp = $pct($a['disk_used'] ?? 0, $a['disk_limit'] ?? 0);
                        $bp = $pct($a['bw_used'] ?? 0, $a['bw_limit'] ?? 0);
                        $issues = [];
                        if ((int) ($a['suspended'] ?? 0) === 1) { $issues[] = '<span class="pg-badge danger">Suspended</span>'; }
                        if ($dp >= 80) { $issues[] = '<span class="pg-badge warn">Disk ' . $dp . '%</span>'; }
                        if ($bp >= 80) { $issues[] = '<span class="pg-badge warn">Bandwidth ' . $bp . '%</span>'; }
                        $sslDays = $a['ssl_days'];
                        if (in_array($a['ssl_status'] ?? '', ['expired','invalid','missing'], true)) {
                            $issues[] = '<span class="pg-badge danger">SSL ' . e((string) $a['ssl_status']) . '</span>';
                        } elseif ($a['ssl_status'] === 'expiring' || ($sslDays !== null && $sslDays <= 30)) {
                            $issues[] = '<span class="pg-badge warn">SSL ' . ($sslDays !== null ? (int) $sslDays . 'd' : 'soon') . '</span>';
                        }
                    ?>
                        <tr>
                            <td><a href="<?= e(url('/accounts/' . (int) $a['id'])) ?>"><?= e($a['domain']) ?></a></td>
                            <td class="flex flex-wrap gap-2"><?= implode(' ', $issues) ?: '<span class="pg-soft">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script type="application/json" id="pg-dashboard-data"><?= json_encode($charts, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
