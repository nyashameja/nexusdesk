<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array{rows:array<int,array<string,mixed>>,total_mailboxes:int,with_data:int,accounts:int} $summary */
$this->layout('layouts.app');
$hasAny = $summary['with_data'] > 0;
?>
<div class="pg-page-head">
    <div>
        <h1>Email Centre</h1>
        <p>Read-only mailbox summary per hosting account.</p>
    </div>
</div>

<div class="pg-alert info">
    <span>Mailbox-level data (per-address quotas and usage) requires the <code>cpanel-api</code> privilege, which the current read-only WHM token may not hold. Account-level counts are shown where available. No mailboxes can be created, changed or deleted in Version&nbsp;1.</span>
</div>

<div class="pg-grid cols-3 mb-3">
    <div class="pg-stat"><div class="label">Accounts</div><div class="value"><?= (int) $summary['accounts'] ?></div></div>
    <div class="pg-stat"><div class="label">Accounts with email data</div><div class="value"><?= (int) $summary['with_data'] ?></div></div>
    <div class="pg-stat"><div class="label">Total mailboxes</div><div class="value"><?= $hasAny ? (int) $summary['total_mailboxes'] : '—' ?></div></div>
</div>

<div class="pg-card">
    <div class="pg-card-head">Per-account email accounts</div>
    <div class="pg-table-wrap">
        <?php if (empty($summary['rows'])): ?>
            <div class="pg-empty"><span class="ic">✉</span><h3>No accounts</h3><p>Run a synchronisation to populate account data.</p></div>
        <?php else: ?>
            <table class="pg-table">
                <thead><tr><th>Domain</th><th>Username</th><th>Email accounts</th><th>Captured</th></tr></thead>
                <tbody>
                <?php foreach ($summary['rows'] as $r): ?>
                    <tr>
                        <td><a href="<?= e(url('/accounts/' . (int) $r['id'])) ?>"><?= e($r['domain']) ?></a></td>
                        <td class="pg-soft"><?= e($r['username']) ?></td>
                        <td><?= $r['email_accounts'] !== null ? (int) $r['email_accounts'] : '<span class="pg-badge neutral">Unavailable with current WHM permissions</span>' ?></td>
                        <td class="pg-soft"><?= !empty($r['captured_at']) ? e(date('d M Y H:i', strtotime((string) $r['captured_at'] . ' UTC'))) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
