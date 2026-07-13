<?php
/** @var array $csat @var int $days */
$chart = json_encode([
    'type' => 'bar',
    'labels' => $csat['labels'],
    'series' => [['name' => 'Ratings', 'data' => $csat['counts']]],
]);
$total = array_sum($csat['counts']);
?>
<div class="crumbs"><a href="/manage/reports">‹ Reports</a></div>
<div class="page-head"><h1>Customer satisfaction</h1></div>
<?= \App\Core\View::render('partials.report_toolbar', ['report' => 'satisfaction', 'days' => $days]) ?>

<div class="tiles" style="margin-bottom:20px">
    <div class="tile"><div class="k">Average rating</div><div class="v tnum"><?= $csat['average'] > 0 ? e($csat['average']) . ' ★' : '—' ?></div></div>
    <div class="tile"><div class="k">Responses</div><div class="v"><?= (int) $total ?></div></div>
</div>

<div class="card"><div class="card-body">
    <?php if ($total === 0): ?>
        <div class="empty">No satisfaction ratings in this period.</div>
    <?php else: ?>
        <canvas data-chart='<?= e($chart) ?>' data-height="260"></canvas>
    <?php endif; ?>
</div></div>
