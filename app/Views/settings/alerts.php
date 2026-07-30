<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var bool $enabled */
/** @var array<int,array{name:string,configured:bool,detail:string,settings:array<int,array{0:string,1:bool,2:string}>}> $channels */
/** @var array<int,string> $recipients */
/** @var array<string,mixed> $thresholds */
/** @var \ParagonHostOps\Services\Auth $auth */
$this->layout('layouts.app');

$anyConfigured = false;
foreach ($channels as $ch) {
    $anyConfigured = $anyConfigured || $ch['configured'];
}

$channelIcon = static fn (string $n): string => match ($n) {
    'email'    => '✉',
    'telegram' => '➤',
    default    => '•',
};
?>
<div class="pg-page-head">
    <div>
        <h1>Alerts</h1>
        <p>Notifications for SSL and domain expiry, and for high disk or bandwidth usage.</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <form method="post" action="<?= e(url('/settings/alerts/run')) ?>" style="margin:0">
            <?= csrf_field() ?>
            <button class="pg-btn" type="submit">Run alert check now</button>
        </form>
        <form method="post" action="<?= e(url('/settings/alerts/test')) ?>" style="margin:0">
            <?= csrf_field() ?>
            <button class="pg-btn primary" type="submit" <?= $anyConfigured ? '' : 'disabled' ?>>Send test alert</button>
        </form>
    </div>
</div>

<?php if (!$enabled): ?>
    <div class="pg-alert warn">
        <span class="pg-alert-ic" aria-hidden="true">!</span>
        <span>
            Alert delivery is <strong>disabled</strong>. Alerts are still detected and shown in-app,
            but nothing is sent. Set <code>ALERTS_ENABLED=true</code> in <code>.env</code> to enable delivery.
        </span>
    </div>
<?php endif; ?>

<div class="pg-grid cols-2 mb-3">
    <?php foreach ($channels as $ch): ?>
        <div class="pg-card">
            <div class="pg-card-head">
                <span><?= e($channelIcon($ch['name'])) ?> <?= e(ucfirst($ch['name'])) ?></span>
                <?= $ch['configured']
                    ? '<span class="pg-badge ok">✓ Configured</span>'
                    : '<span class="pg-badge neutral">Not configured</span>' ?>
            </div>
            <div class="pg-card-body">
                <p class="pg-soft mt-0" style="font-size:13px"><?= e($ch['detail']) ?></p>
                <?php if ($ch['settings'] !== []): ?>
                    <ul class="pg-status-list">
                        <?php foreach ($ch['settings'] as [$label, $present, $note]): ?>
                            <li>
                                <span class="name"><?= e($label) ?></span>
                                <span class="flex items-center gap-2">
                                    <span class="pg-muted" style="font-size:11.5px"><?= e($note) ?></span>
                                    <?= $present
                                        ? '<span class="pg-badge ok">Set</span>'
                                        : '<span class="pg-badge warn">Missing</span>' ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="pg-card-foot">
                Secret values are read from <code>.env</code> and are never displayed here.
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="pg-grid cols-2">
    <div class="pg-card">
        <div class="pg-card-head">Thresholds</div>
        <div class="pg-table-wrap">
            <table class="pg-table">
                <tbody>
                    <tr>
                        <td class="pg-soft" style="width:45%">Domain expiry</td>
                        <td><?= e(implode(', ', $thresholds['domain_days'] ?? [])) ?> days before expiry</td>
                    </tr>
                    <tr>
                        <td class="pg-soft">SSL expiry</td>
                        <td><?= e(implode(', ', $thresholds['ssl_days'] ?? [])) ?> days before expiry</td>
                    </tr>
                    <tr>
                        <td class="pg-soft">Disk &amp; bandwidth</td>
                        <td><?= e(implode('%, ', $thresholds['usage_pct'] ?? [])) ?>%</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="pg-card-foot">
            Each threshold sends <strong>once per crossing</strong> and re-arms automatically when the
            condition clears — for example when a domain is renewed.
        </div>
    </div>

    <div class="pg-card">
        <div class="pg-card-head">What triggers an alert</div>
        <div class="pg-card-body">
            <ul class="pg-soft" style="font-size:13px;margin:0;padding-left:18px;line-height:1.9">
                <li><strong>🔐 SSL certificates</strong> — expiring within 30, 15 or 5 days, or already expired.</li>
                <li><strong>🌍 Domains</strong> — expiring within 30, 15 or 5 days, or already expired.</li>
                <li><strong>💾 Disk usage</strong> — at or above 80%, then 95%.</li>
                <li><strong>📶 Bandwidth usage</strong> — at or above 80%, then 95%.</li>
            </ul>
            <p class="pg-muted mt-2 mb-0" style="font-size:11.5px">
                Alerts run automatically with the nightly synchronisation, or via the
                <code>alerts</code> cron job. Multiple alerts from one run are grouped into a single digest.
            </p>
        </div>
    </div>
</div>
