<?php
/** @var int $openTickets @var array<int,\App\Models\Ticket> $recent */
$user = auth();
?>
<div class="page-head">
    <div><h1>Welcome back, <?= e($user?->firstName ?? '') ?></h1></div>
    <a class="btn btn-primary" href="/portal/tickets/new">+ New ticket</a>
</div>

<?php /** @var array $finance @var bool $financeConnected */ ?>
<div class="tiles" style="margin-bottom:20px">
    <div class="tile"><div class="k">Open tickets</div><div class="v"><?= (int) $openTickets ?></div></div>
    <div class="tile">
        <div class="k">Invoices due</div>
        <?php if ($financeConnected): ?>
            <div class="v tnum" style="color:<?= $finance['balance'] > 0 ? 'var(--warning)' : 'var(--success)' ?>"><?= e(money($finance['balance'])) ?></div>
            <div class="muted" style="font-size:12px"><?= (int) $finance['outstanding_count'] ?> open · <a href="/portal/invoices">view</a></div>
        <?php else: ?>
            <div class="v tnum">—</div><div class="muted" style="font-size:12px">Finance not connected</div>
        <?php endif; ?>
    </div>
    <div class="tile"><div class="k">Total invoices</div><div class="v"><?= $financeConnected ? (int) $finance['invoice_count'] : '—' ?></div></div>
</div>

<div class="card">
    <div class="card-head"><h2>Recent tickets</h2><a href="/portal/tickets" class="muted" style="font-size:13px">All →</a></div>
    <div class="card-body" style="padding:0">
        <?php if ($recent === []): ?>
            <div class="empty">You haven't opened any tickets yet. <a href="/portal/tickets/new">Create one →</a></div>
        <?php else: ?>
            <div class="table-wrap" style="border:0">
                <table class="data"><tbody>
                <?php foreach ($recent as $t): ?>
                    <tr onclick="location='/portal/tickets/<?= $t->id() ?>'" style="cursor:pointer">
                        <td style="width:110px"><a class="ref" href="/portal/tickets/<?= $t->id() ?>"><?= e($t->reference()) ?></a></td>
                        <td><?= e($t->subject()) ?></td>
                        <td style="width:150px"><span class="pill" style="background:color-mix(in srgb,<?= e($t->statusColour()) ?> 15%,transparent);color:<?= e($t->statusColour()) ?>"><span class="d"></span><?= e($t->statusName()) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
        <?php endif; ?>
    </div>
</div>
