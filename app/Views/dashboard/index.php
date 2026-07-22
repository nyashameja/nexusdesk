<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<string,int|float> $summary */
/** @var array{valid:int,expiring:int,issues:int} $ssl */
/** @var int $packageCount */
/** @var string|null $lastSync */
/** @var bool $whmConfigured */
$this->layout('layouts.app');

$fmtGb = static fn (float $mb): string => number_format($mb / 1024, 1) . ' GB';
$hasData = ($summary['total'] ?? 0) > 0;
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
                    No cached hosting data yet. Once WHM credentials are configured and a synchronisation
                    has run, live figures will appear here. Until then all counters read zero.
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

<div class="pg-grid cols-2">
    <div class="pg-card">
        <div class="pg-card-head">Accounts requiring attention</div>
        <div class="pg-card-body">
            <?php if (!$hasData): ?>
                <div class="pg-empty"><span class="ic">◎</span><h3>Nothing to show yet</h3><p>Attention items appear here after the first synchronisation.</p></div>
            <?php else: ?>
                <p class="pg-soft mb-0">High-usage, suspended and SSL-attention accounts are listed in the
                    <a href="<?= e(url('/accounts')) ?>">Hosting Accounts</a> module with dedicated filters.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="pg-card">
        <div class="pg-card-head">Quick filters</div>
        <div class="pg-card-body flex flex-wrap gap-2">
            <a class="pg-btn" href="<?= e(url('/accounts')) ?>">All accounts</a>
            <a class="pg-btn" href="<?= e(url('/accounts?filter=active')) ?>">Active</a>
            <a class="pg-btn" href="<?= e(url('/accounts?filter=suspended')) ?>">Suspended</a>
            <a class="pg-btn" href="<?= e(url('/accounts?filter=high_disk')) ?>">High disk</a>
            <a class="pg-btn" href="<?= e(url('/accounts?filter=ssl')) ?>">SSL attention</a>
        </div>
    </div>
</div>
