<?php /** @var bool $connected @var array $quotes */ ?>
<div class="page-head"><h1>Quotes</h1></div>
<?php if (!$connected): ?>
    <div class="alert alert-info">Finance data isn't connected yet.</div>
<?php endif; ?>
<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Number</th><th>Date</th><th>Status</th><th style="text-align:right">Total</th></tr></thead>
        <tbody>
        <?php if ($quotes === []): ?>
            <tr><td colspan="4"><div class="empty">No quotes to show.</div></td></tr>
        <?php else: foreach ($quotes as $q): ?>
            <tr>
                <td class="ref"><?= e($q['number']) ?></td>
                <td class="muted tnum"><?= e($q['issue_date'] ?? '—') ?></td>
                <td><span class="pill plain"><span class="d"></span><?= e(ucfirst((string) $q['status'])) ?></span></td>
                <td class="tnum" style="text-align:right"><?= e(money($q['total'], (string) $q['currency'])) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
