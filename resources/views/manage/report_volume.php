<?php
/** @var array $volume @var int $days */
$chart = json_encode([
    'type' => 'line',
    'labels' => $volume['labels'],
    'series' => [
        ['name' => 'Created', 'data' => $volume['created']],
        ['name' => 'Resolved', 'data' => $volume['resolved']],
    ],
]);
$totalCreated = array_sum($volume['created']);
$totalResolved = array_sum($volume['resolved']);
?>
<div class="crumbs"><a href="/manage/reports">‹ Reports</a></div>
<div class="page-head"><h1>Ticket volume</h1></div>
<?= \App\Core\View::render('partials.report_toolbar', ['report' => 'volume', 'days' => $days]) ?>

<div class="tiles" style="margin-bottom:20px">
    <div class="tile"><div class="k">Created</div><div class="v"><?= (int) $totalCreated ?></div></div>
    <div class="tile"><div class="k">Resolved</div><div class="v" style="color:var(--success)"><?= (int) $totalResolved ?></div></div>
</div>

<div class="card"><div class="card-body">
    <canvas data-chart='<?= e($chart) ?>' data-height="300"></canvas>
    <div style="display:flex;gap:18px;margin-top:12px;font-size:12px" class="muted">
        <span>● Created</span><span style="color:var(--accent)">● Resolved</span>
    </div>
</div></div>
