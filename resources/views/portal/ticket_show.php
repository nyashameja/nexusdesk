<?php
/** @var \App\Models\Ticket $ticket @var array $messages */
$id = $ticket->id();
?>
<div class="crumbs"><a href="/portal/tickets">‹ My tickets</a></div>
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
                <div class="who"><?= e($m['author_name'] ?: ($m['author_type'] === 'agent' ? 'Support' : 'You')) ?> · <?= e($m['created_at']) ?></div>
                <?= $m['body_html'] ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$ticket->isResolved()): ?>
        <form method="post" action="/portal/tickets/<?= $id ?>/reply">
            <?= csrf_field() ?>
            <div class="field" style="margin-bottom:10px">
                <textarea class="textarea" name="body" placeholder="Add a reply…" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Send reply</button>
        </form>
    <?php else: ?>
        <p class="muted" style="margin:0">This ticket is resolved. Reply is disabled — open a new ticket if you need more help.</p>
    <?php endif; ?>
</div></div>
