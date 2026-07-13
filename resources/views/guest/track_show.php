<?php
/** @var \App\Models\Ticket $ticket @var array<int,array<string,mixed>> $messages */
?>
<div class="crumbs"><a href="/track">← Track another</a></div>
<div class="page-head">
    <div>
        <div class="ref tnum"><?= e($ticket->reference()) ?></div>
        <h1><?= e($ticket->subject()) ?></h1>
    </div>
    <span class="pill" style="background:color-mix(in srgb,<?= e($ticket->statusColour()) ?> 15%,transparent);color:<?= e($ticket->statusColour()) ?>">
        <span class="d"></span><?= e($ticket->statusName()) ?>
    </span>
</div>

<div class="card"><div class="card-body">
    <div class="conv">
        <?php foreach ($messages as $m): ?>
            <div class="msg <?= $m['author_type'] === 'agent' ? 'agent' : 'cust' ?>">
                <div class="who"><?= e($m['author_name'] ?: ($m['author_type'] === 'agent' ? 'Support' : $ticket->requesterName())) ?> · <?= e($m['created_at']) ?></div>
                <?= $m['body_html'] ?>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="muted" style="margin:0">To reply, please <a href="/login">sign in to the portal</a>.</p>
</div></div>
