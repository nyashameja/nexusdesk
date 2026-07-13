<?php
/** @var array<string,int> $counts @var array<int,\App\Models\Ticket> $myQueue @var array<int,\App\Models\Ticket> $atRisk */
$user = auth();
?>
<div class="page-head">
    <div><h1>Good day, <?= e($user?->firstName ?? 'there') ?></h1></div>
    <a class="btn btn-primary" href="/desk/tickets">View all tickets</a>
</div>

<div class="tiles" style="margin-bottom:20px">
    <div class="tile"><div class="k">Open tickets</div><div class="v"><?= (int) ($counts['open'] ?? 0) ?></div>
        <div class="spark"><?php for ($i=0;$i<12;$i++): ?><span style="height:<?= random_int(30,100) ?>%"></span><?php endfor; ?></div></div>
    <div class="tile"><div class="k">Assigned to me</div><div class="v"><?= (int) ($counts['mine'] ?? 0) ?></div></div>
    <div class="tile"><div class="k">SLA at-risk</div><div class="v" style="color:var(--warning)"><?= (int) ($counts['atRisk'] ?? 0) ?></div></div>
    <div class="tile"><div class="k">Resolved today</div><div class="v" style="color:var(--success)"><?= (int) ($counts['resolvedToday'] ?? 0) ?></div></div>
</div>

<div class="grid" style="grid-template-columns:1.6fr 1fr">
    <div class="card">
        <div class="card-head"><h2>My queue</h2><a href="/desk/tickets?assigned=me" class="muted" style="font-size:13px">All →</a></div>
        <div class="card-body" style="padding:0">
            <?php if ($myQueue === []): ?>
                <div class="empty">Nothing assigned to you right now. 🎉</div>
            <?php else: ?>
                <?= \App\Core\View::render('desk.partials_ticket_rows', ['tickets' => $myQueue]) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>SLA at-risk</h2></div>
        <div class="card-body" style="padding:0">
            <?php if ($atRisk === []): ?>
                <div class="empty">No tickets at risk.</div>
            <?php else: ?>
                <?= \App\Core\View::render('desk.partials_ticket_rows', ['tickets' => $atRisk]) ?>
            <?php endif; ?>
        </div>
    </div>
</div>
