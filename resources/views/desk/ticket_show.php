<?php
/** @var \App\Models\Ticket $ticket @var array $messages @var array $statuses @var array $priorities @var array $departments */
/** @var int $timeMinutes @var int|null $slaRemaining */
$id = $ticket->id();
$slaClass = 'ok';
$slaLabel = 'On track';
if ($slaRemaining !== null) {
    if ($slaRemaining < 0) { $slaClass = 'bad'; $slaLabel = 'Breached'; }
    elseif ($slaRemaining < 120) { $slaClass = 'warn'; $slaLabel = $slaRemaining . 'm left'; }
    else { $slaLabel = intdiv($slaRemaining, 60) . 'h ' . ($slaRemaining % 60) . 'm left'; }
}
?>
<div class="crumbs"><a href="/desk/tickets">‹ Queue</a></div>
<div class="page-head">
    <div>
        <div class="ref tnum"><?= e($ticket->reference()) ?></div>
        <h1><?= e($ticket->subject()) ?></h1>
    </div>
</div>

<div class="workspace">
    <div>
        <div class="card"><div class="card-body">
            <div class="conv">
                <?php foreach ($messages as $m): ?>
                    <?php $cls = $m['is_internal'] ? 'note' : ($m['author_type'] === 'agent' ? 'agent' : ($m['author_type'] === 'system' ? 'system' : 'cust')); ?>
                    <div class="msg <?= $cls ?>">
                        <div class="who">
                            <?= $m['is_internal'] ? '🔒 Internal · ' : '' ?><?= e($m['author_name'] ?: ucfirst($m['author_type'])) ?> · <?= e($m['created_at']) ?>
                        </div>
                        <?= $m['body_html'] ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post" action="/desk/tickets/<?= $id ?>/reply" data-reply-box>
                <?= csrf_field() ?>
                <div class="reply-tools">
                    <span class="chip">Canned ▾</span>
                    <span class="chip ai">🤖 AI ▾</span>
                    <span class="chip">📎 Attach</span>
                </div>
                <div class="field" style="margin-bottom:10px">
                    <textarea class="textarea" name="body" placeholder="Write a reply…" required></textarea>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
                    <label style="display:flex;gap:8px;align-items:center;font-size:13px">
                        <input type="checkbox" name="internal" value="1" data-note-toggle> Internal note
                    </label>
                    <div style="display:flex;gap:8px">
                        <select class="select" name="status" style="width:auto">
                            <option value="">Keep status</option>
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= e($s['slug']) ?>"><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Send reply</button>
                    </div>
                </div>
            </form>
        </div></div>
    </div>

    <aside class="rail">
        <div class="rail-card">
            <div class="lab">Requester</div>
            <div style="font-weight:600"><?= e($ticket->requesterName()) ?></div>
            <div class="muted" style="font-size:13px"><?= e($ticket->requesterEmail() ?? '') ?></div>
            <?php if ($ticket->companyName()): ?><div class="muted" style="font-size:13px"><?= e($ticket->companyName()) ?></div><?php endif; ?>
        </div>

        <div class="rail-card">
            <div class="lab">SLA — Resolution</div>
            <div class="meter <?= $slaClass ?>"><i style="width:<?= $slaClass === 'ok' ? 45 : ($slaClass === 'warn' ? 78 : 100) ?>%"></i></div>
            <div class="<?= $slaClass === 'bad' ? '' : 'muted' ?> tnum" style="font-size:12px;margin-top:6px;<?= $slaClass==='bad'?'color:var(--danger)':'' ?>"><?= e($slaLabel) ?></div>
        </div>

        <div class="rail-card">
            <div class="kv"><span>Status</span><b><?= e($ticket->statusName()) ?></b></div>
            <div class="kv"><span>Priority</span><b style="color:<?= e($ticket->priorityColour()) ?>"><?= e($ticket->priorityName()) ?></b></div>
            <div class="kv"><span>Department</span><b><?= e($ticket->departmentName()) ?></b></div>
            <div class="kv"><span>Assignee</span><b><?= e($ticket->assignedAgentName() ?? 'Unassigned') ?></b></div>
            <div class="kv"><span>Time logged</span><b class="tnum"><?= intdiv($timeMinutes, 60) ?>h <?= $timeMinutes % 60 ?>m</b></div>
        </div>

        <div class="rail-card">
            <div class="lab">Actions</div>
            <form method="post" action="/desk/tickets/<?= $id ?>/transfer" style="margin-bottom:10px">
                <?= csrf_field() ?>
                <div style="display:flex;gap:6px">
                    <select class="select" name="department_id">
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int) $d['id'] ?>" <?= (int) $d['id'] === $ticket->departmentId() ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-ghost btn-sm" type="submit">Transfer</button>
                </div>
            </form>
            <form method="post" action="/desk/tickets/<?= $id ?>/time" style="display:flex;gap:6px">
                <?= csrf_field() ?>
                <input class="input" type="number" name="minutes" min="1" placeholder="min" style="width:80px">
                <button class="btn btn-ghost btn-sm" type="submit">Log time</button>
            </form>
        </div>
    </aside>
</div>
