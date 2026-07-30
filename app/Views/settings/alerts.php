<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var bool $enabled, $configured */
/** @var array<int,string> $recipients */
/** @var array<string,mixed> $thresholds */
/** @var \ParagonHostOps\Services\Auth $auth */
$this->layout('layouts.app');
?>
<div class="pg-page-head">
    <div>
        <h1>Alerts</h1>
        <p>Email notifications for SSL/domain expiry and high disk/bandwidth usage. WhatsApp can be added later.</p>
    </div>
</div>

<div class="pg-grid cols-2 mb-3">
    <div class="pg-card">
        <div class="pg-card-head">Status</div>
        <div class="pg-table-wrap">
            <table class="pg-table"><tbody>
                <tr><td class="pg-soft" style="width:45%">Email alerts</td><td>
                    <?= $enabled ? '<span class="pg-badge ok">Enabled</span>' : '<span class="pg-badge warn">Disabled</span>' ?>
                    <?php if (!$enabled): ?><span class="pg-muted" style="font-size:12px"> — set <code>ALERTS_ENABLED=true</code> in .env</span><?php endif; ?>
                </td></tr>
                <tr><td class="pg-soft">Recipients</td><td>
                    <?php if ($recipients): ?>
                        <?= e(implode(', ', $recipients)) ?>
                    <?php else: ?>
                        <span class="pg-badge danger">None</span> <span class="pg-muted" style="font-size:12px">set <code>ALERT_EMAIL_TO</code> in .env</span>
                    <?php endif; ?>
                </td></tr>
                <tr><td class="pg-soft">Domain / SSL thresholds</td><td class="pg-soft"><?= e(implode(', ', $thresholds['domain_days'] ?? [])) ?> days</td></tr>
                <tr><td class="pg-soft">Usage thresholds</td><td class="pg-soft"><?= e(implode('%, ', $thresholds['usage_pct'] ?? [])) ?>%</td></tr>
            </tbody></table>
        </div>
    </div>

    <div class="pg-card">
        <div class="pg-card-head">Actions</div>
        <div class="pg-card-body">
            <p class="pg-soft">Run a check now, or send a test email to confirm delivery.</p>
            <div class="flex gap-2 flex-wrap">
                <form method="post" action="<?= e(url('/settings/alerts/run')) ?>" style="margin:0"><?= csrf_field() ?><button class="pg-btn primary" type="submit">Run alert check now</button></form>
                <form method="post" action="<?= e(url('/settings/alerts/test')) ?>" style="margin:0"><?= csrf_field() ?><button class="pg-btn" type="submit" <?= $configured ? '' : 'disabled' ?>>Send test email</button></form>
            </div>
            <p class="pg-muted mt-2 mb-0" style="font-size:11.5px">
                Alerts also run automatically with the nightly synchronisation, or via the
                <code>alerts</code> cron job. A digest is sent only when a threshold is newly crossed.
            </p>
        </div>
    </div>
</div>

<div class="pg-card">
    <div class="pg-card-head">What triggers an alert</div>
    <div class="pg-card-body">
        <ul class="pg-soft" style="font-size:13px;margin:0;padding-left:18px;line-height:1.9">
            <li><strong>SSL certificates</strong> — expiring within 30, 15 or 5 days, or already expired.</li>
            <li><strong>Domains</strong> — expiring within 30, 15 or 5 days, or already expired.</li>
            <li><strong>Disk usage</strong> — at or above 80%, then 95%.</li>
            <li><strong>Bandwidth usage</strong> — at or above 80%, then 95%.</li>
        </ul>
        <p class="pg-muted mt-2 mb-0" style="font-size:11.5px">Each threshold fires once per crossing and re-arms automatically when the condition clears (e.g. a renewed domain).</p>
    </div>
</div>
