<?php /** @var bool $connected @var array $payments @var array $creditNotes @var array $summary */ ?>
<div class="page-head"><h1>Statements</h1></div>
<?php if (!$connected): ?>
    <div class="alert alert-info">Finance data isn't connected yet.</div>
<?php endif; ?>

<div class="tiles" style="margin-bottom:20px">
    <div class="tile"><div class="k">Outstanding balance</div><div class="v tnum" style="color:<?= $summary['balance'] > 0 ? 'var(--warning)' : 'var(--success)' ?>"><?= e(money($summary['balance'])) ?></div></div>
</div>

<div class="grid" style="grid-template-columns:1fr 1fr">
    <div class="card">
        <div class="card-head"><h2>Payments</h2></div>
        <div class="card-body" style="padding:0">
            <?php if ($payments === []): ?><div class="empty">No payments recorded.</div>
            <?php else: ?><div class="table-wrap" style="border:0"><table class="data"><tbody>
                <?php foreach ($payments as $p): ?>
                    <tr><td class="ref"><?= e($p['number']) ?></td><td class="muted tnum"><?= e($p['issue_date'] ?? '—') ?></td><td class="tnum" style="text-align:right"><?= e(money($p['total'], (string) $p['currency'])) ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Credit notes</h2></div>
        <div class="card-body" style="padding:0">
            <?php if ($creditNotes === []): ?><div class="empty">No credit notes.</div>
            <?php else: ?><div class="table-wrap" style="border:0"><table class="data"><tbody>
                <?php foreach ($creditNotes as $cn): ?>
                    <tr><td class="ref"><?= e($cn['number']) ?></td><td class="muted tnum"><?= e($cn['issue_date'] ?? '—') ?></td><td class="tnum" style="text-align:right"><?= e(money($cn['total'], (string) $cn['currency'])) ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </div>
    </div>
</div>
