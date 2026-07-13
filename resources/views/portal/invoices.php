<?php /** @var bool $connected @var array $invoices @var array $summary */ ?>
<div class="page-head"><h1>Invoices</h1></div>

<?php if (!$connected): ?>
    <div class="alert alert-info">Finance data isn't connected yet. Your account manager can link Zoho Books to show your invoices here.</div>
<?php endif; ?>

<div class="tiles" style="margin-bottom:20px">
    <div class="tile"><div class="k">Outstanding balance</div><div class="v tnum" style="color:<?= $summary['balance'] > 0 ? 'var(--warning)' : 'var(--success)' ?>"><?= e(money($summary['balance'])) ?></div></div>
    <div class="tile"><div class="k">Open invoices</div><div class="v"><?= (int) $summary['outstanding_count'] ?></div></div>
    <div class="tile"><div class="k">Total invoices</div><div class="v"><?= (int) $summary['invoice_count'] ?></div></div>
</div>

<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Number</th><th>Date</th><th>Due</th><th>Status</th><th style="text-align:right">Total</th><th style="text-align:right">Balance</th><th></th></tr></thead>
        <tbody>
        <?php if ($invoices === []): ?>
            <tr><td colspan="7"><div class="empty">No invoices to show.</div></td></tr>
        <?php else: foreach ($invoices as $inv): ?>
            <tr>
                <td><a class="ref" href="/portal/invoices/<?= (int) $inv['id'] ?>"><?= e($inv['number']) ?></a></td>
                <td class="muted tnum"><?= e($inv['issue_date'] ?? '—') ?></td>
                <td class="muted tnum"><?= e($inv['due_date'] ?? '—') ?></td>
                <td><span class="pill plain"><span class="d"></span><?= e(ucfirst((string) $inv['status'])) ?></span></td>
                <td class="tnum" style="text-align:right"><?= e(money($inv['total'], (string) $inv['currency'])) ?></td>
                <td class="tnum" style="text-align:right;color:<?= (float) $inv['balance'] > 0 ? 'var(--warning)' : 'inherit' ?>"><?= e(money($inv['balance'], (string) $inv['currency'])) ?></td>
                <td style="text-align:right"><a class="btn btn-ghost btn-sm" href="/portal/invoices/<?= (int) $inv['id'] ?>/pdf" target="_blank">PDF</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
