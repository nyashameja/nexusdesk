<?php
/** @var array<int,\App\Models\Ticket> $tickets @var int $total @var int $page @var int $perPage @var array $statuses */
?>
<div class="page-head">
    <div><h1>My tickets</h1><div class="muted"><?= (int) $total ?> total</div></div>
    <a class="btn btn-primary" href="/portal/tickets/new">+ New ticket</a>
</div>

<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Ref</th><th>Subject</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
        <?php if ($tickets === []): ?>
            <tr><td colspan="4"><div class="empty">No tickets yet.</div></td></tr>
        <?php else: foreach ($tickets as $t): ?>
            <tr onclick="location='/portal/tickets/<?= $t->id() ?>'" style="cursor:pointer">
                <td><a class="ref" href="/portal/tickets/<?= $t->id() ?>"><?= e($t->reference()) ?></a></td>
                <td><?= e($t->subject()) ?></td>
                <td><span class="pill" style="background:color-mix(in srgb,<?= e($t->statusColour()) ?> 15%,transparent);color:<?= e($t->statusColour()) ?>"><span class="d"></span><?= e($t->statusName()) ?></span></td>
                <td class="muted tnum" style="font-size:12px"><?= e(substr($t->createdAt(), 0, 10)) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?= \App\Core\View::render('partials.pagination', ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'baseUrl' => '/portal/tickets']) ?>
