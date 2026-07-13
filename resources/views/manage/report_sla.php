<?php
/** @var array $sla @var array $departments @var int $days */
$chart = json_encode([
    'type' => 'doughnut',
    'labels' => ['Met', 'Breached'],
    'series' => [['name' => 'SLA', 'data' => [$sla['compliant'], $sla['breached']]]],
]);
?>
<div class="crumbs"><a href="/manage/reports">‹ Reports</a></div>
<div class="page-head"><h1>SLA compliance</h1></div>
<?= \App\Core\View::render('partials.report_toolbar', ['report' => 'sla', 'days' => $days]) ?>

<div class="grid" style="grid-template-columns:1fr 2fr">
    <div class="card">
        <div class="card-head"><h2><?= e($sla['pct']) ?>% compliant</h2></div>
        <div class="card-body"><canvas data-chart='<?= e($chart) ?>' data-height="200"></canvas>
            <div style="display:flex;gap:18px;justify-content:center;margin-top:10px;font-size:12px" class="muted">
                <span>● Met (<?= (int) $sla['compliant'] ?>)</span><span style="color:var(--accent)">● Breached (<?= (int) $sla['breached'] ?>)</span>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>By department</h2></div>
        <div class="card-body" style="padding:0">
            <div class="table-wrap" style="border:0"><table class="data">
                <thead><tr><th>Department</th><th style="text-align:right">Total</th><th style="text-align:right">Resolved</th></tr></thead>
                <tbody>
                <?php foreach ($departments as $d): ?>
                    <tr><td><?= e($d['name']) ?></td><td class="tnum" style="text-align:right"><?= (int) $d['total'] ?></td><td class="tnum" style="text-align:right"><?= (int) $d['resolved'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>
