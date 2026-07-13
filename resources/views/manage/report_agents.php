<?php
/** @var array $agents @var int $days */
$chart = json_encode([
    'type' => 'bar',
    'labels' => array_map(static fn ($a) => explode(' ', (string) $a['name'])[0], $agents),
    'series' => [['name' => 'Resolved', 'data' => array_map(static fn ($a) => (int) $a['resolved'], $agents)]],
]);
?>
<div class="crumbs"><a href="/manage/reports">‹ Reports</a></div>
<div class="page-head"><h1>Agent performance</h1></div>
<?= \App\Core\View::render('partials.report_toolbar', ['report' => 'agents', 'days' => $days]) ?>

<?php if ($agents !== []): ?>
    <div class="card" style="margin-bottom:16px"><div class="card-body">
        <canvas data-chart='<?= e($chart) ?>' data-height="240"></canvas>
    </div></div>
<?php endif; ?>

<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Agent</th><th style="text-align:right">Assigned</th><th style="text-align:right">Resolved</th><th style="text-align:right">Avg hours</th><th style="text-align:right">CSAT</th></tr></thead>
        <tbody>
        <?php if ($agents === []): ?>
            <tr><td colspan="5"><div class="empty">No agent activity in this period.</div></td></tr>
        <?php else: foreach ($agents as $a): ?>
            <tr>
                <td><strong><?= e($a['name']) ?></strong></td>
                <td class="tnum" style="text-align:right"><?= (int) $a['assigned'] ?></td>
                <td class="tnum" style="text-align:right"><?= (int) $a['resolved'] ?></td>
                <td class="tnum" style="text-align:right"><?= e($a['avg_hours'] ?? '—') ?></td>
                <td class="tnum" style="text-align:right"><?= $a['csat'] ? e($a['csat']) . ' ★' : '—' ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
