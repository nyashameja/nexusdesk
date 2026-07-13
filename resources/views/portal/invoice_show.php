<?php /** @var array $invoice @var array $payload */
$items = $payload['line_items'] ?? [];
?>
<div class="crumbs"><a href="/portal/invoices">‹ Invoices</a></div>
<div class="page-head">
    <div><div class="ref tnum"><?= e($invoice['number']) ?></div><h1>Invoice</h1></div>
    <a class="btn btn-primary" href="/portal/invoices/<?= (int) $invoice['id'] ?>/pdf" target="_blank">Download PDF</a>
</div>

<div class="grid" style="grid-template-columns:2fr 1fr">
    <div class="card"><div class="card-body">
        <?php if ($items === []): ?>
            <p class="muted">Line items appear on the PDF. This portal shows a summary of your Zoho Books invoice.</p>
        <?php else: ?>
            <div class="table-wrap" style="border:0">
                <table class="data"><thead><tr><th>Item</th><th style="text-align:right">Qty</th><th style="text-align:right">Rate</th><th style="text-align:right">Amount</th></tr></thead>
                <tbody>
                <?php foreach ($items as $li): ?>
                    <tr>
                        <td><?= e($li['name'] ?? ($li['description'] ?? '')) ?></td>
                        <td class="tnum" style="text-align:right"><?= e((string) ($li['quantity'] ?? '')) ?></td>
                        <td class="tnum" style="text-align:right"><?= e(money((float) ($li['rate'] ?? 0), (string) $invoice['currency'])) ?></td>
                        <td class="tnum" style="text-align:right"><?= e(money((float) ($li['item_total'] ?? 0), (string) $invoice['currency'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
        <?php endif; ?>
    </div></div>
    <div class="card"><div class="card-body">
        <div class="kv"><span>Status</span><b><?= e(ucfirst((string) $invoice['status'])) ?></b></div>
        <div class="kv"><span>Issued</span><b class="tnum"><?= e($invoice['issue_date'] ?? '—') ?></b></div>
        <div class="kv"><span>Due</span><b class="tnum"><?= e($invoice['due_date'] ?? '—') ?></b></div>
        <div class="kv"><span>Total</span><b class="tnum"><?= e(money($invoice['total'], (string) $invoice['currency'])) ?></b></div>
        <div class="kv"><span>Balance</span><b class="tnum" style="color:<?= (float) $invoice['balance'] > 0 ? 'var(--warning)' : 'var(--success)' ?>"><?= e(money($invoice['balance'], (string) $invoice['currency'])) ?></b></div>
    </div></div>
</div>
