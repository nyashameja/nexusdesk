<?php
/** @var array<int,\App\Models\Ticket> $tickets @var int $total @var int $page @var int $perPage */
/** @var array<string,mixed> $filters @var array $statuses @var array $priorities @var array $departments */
$qs = http_build_query(array_filter($filters));
?>
<div class="page-head">
    <div><h1>Tickets</h1><div class="muted"><?= (int) $total ?> total</div></div>
    <a class="btn btn-primary" href="/desk/tickets/new">+ New ticket</a>
</div>

<div class="card" style="margin-bottom:16px"><div class="card-body" style="padding:14px 16px">
    <form method="get" action="/desk/tickets" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:0">
        <div class="field" style="margin:0;flex:1;min-width:180px">
            <label>Search</label>
            <input class="input" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Reference or subject">
        </div>
        <div class="field" style="margin:0">
            <label>Status</label>
            <select class="select" name="status">
                <option value="">All</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= e($s['slug']) ?>" <?= (($filters['status'] ?? '') === $s['slug']) ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0">
            <label>Priority</label>
            <select class="select" name="priority">
                <option value="">All</option>
                <?php foreach ($priorities as $p): ?>
                    <option value="<?= e($p['slug']) ?>" <?= (($filters['priority'] ?? '') === $p['slug']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Filter</button>
        <a class="btn btn-ghost" href="/desk/tickets">Reset</a>
    </form>
</div></div>

<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Ref</th><th>Subject</th><th>Requester</th><th>Priority</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
        <?php if ($tickets === []): ?>
            <tr><td colspan="6"><div class="empty">No tickets match these filters.</div></td></tr>
        <?php else: foreach ($tickets as $t): ?>
            <tr onclick="location='/desk/tickets/<?= $t->id() ?>'" style="cursor:pointer">
                <td><a class="ref" href="/desk/tickets/<?= $t->id() ?>"><?= e($t->reference()) ?></a></td>
                <td><?= e($t->subject()) ?></td>
                <td><?= e($t->requesterName()) ?></td>
                <td><span class="pill" style="background:color-mix(in srgb,<?= e($t->priorityColour()) ?> 15%,transparent);color:<?= e($t->priorityColour()) ?>"><span class="d"></span><?= e($t->priorityName()) ?></span></td>
                <td><span class="pill" style="background:color-mix(in srgb,<?= e($t->statusColour()) ?> 15%,transparent);color:<?= e($t->statusColour()) ?>"><span class="d"></span><?= e($t->statusName()) ?></span></td>
                <td class="muted tnum" style="font-size:12px"><?= e(substr($t->createdAt(), 0, 10)) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?= \App\Core\View::render('partials.pagination', ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'baseUrl' => '/desk/tickets' . ($qs ? '?' . $qs : '')]) ?>
