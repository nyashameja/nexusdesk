<?php
/** @var array $summary @var array $volume @var array $departments @var array $statuses */
$volumeChart = json_encode([
    'type' => 'line',
    'labels' => $volume['labels'],
    'series' => [
        ['name' => 'Created', 'data' => $volume['created']],
        ['name' => 'Resolved', 'data' => $volume['resolved']],
    ],
]);
$statusChart = json_encode([
    'type' => 'doughnut',
    'labels' => $statuses['labels'],
    'series' => [['name' => 'Tickets', 'data' => $statuses['counts']]],
]);
?>
<div class="page-head">
    <div><h1>Manager dashboard</h1><div class="muted">Last 30 days</div></div>
    <a class="btn btn-primary" href="/manage/reports">All reports</a>
</div>

<div class="tiles" style="margin-bottom:20px">
    <div class="tile"><div class="k">Tickets (30d)</div><div class="v"><?= (int) $summary['total'] ?></div></div>
    <div class="tile"><div class="k">SLA compliance</div><div class="v tnum" style="color:<?= $summary['sla_compliance'] >= 90 ? 'var(--success)' : 'var(--warning)' ?>"><?= e($summary['sla_compliance']) ?>%</div></div>
    <div class="tile"><div class="k">Avg resolution</div><div class="v tnum"><?= e($summary['avg_resolution_hours']) ?>h</div></div>
    <div class="tile"><div class="k">CSAT</div><div class="v tnum"><?= $summary['csat_average'] > 0 ? e($summary['csat_average']) . ' ★' : '—' ?></div></div>
</div>

<div class="grid" style="grid-template-columns:2fr 1fr">
    <div class="card">
        <div class="card-head"><h2>Ticket volume</h2><a class="muted" style="font-size:13px" href="/manage/reports/volume">Detail →</a></div>
        <div class="card-body"><canvas data-chart='<?= e($volumeChart) ?>' data-height="240"></canvas></div>
    </div>
    <div class="card">
        <div class="card-head"><h2>By status</h2></div>
        <div class="card-body"><canvas data-chart='<?= e($statusChart) ?>' data-height="240"></canvas></div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="card-head"><h2>Department performance</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap" style="border:0"><table class="data">
            <thead><tr><th>Department</th><th style="text-align:right">Total</th><th style="text-align:right">Resolved</th><th style="text-align:right">Resolution rate</th></tr></thead>
            <tbody>
            <?php foreach ($departments as $d): $rate = (int) $d['total'] > 0 ? round((int) $d['resolved'] / (int) $d['total'] * 100) : 0; ?>
                <tr>
                    <td><strong><?= e($d['name']) ?></strong></td>
                    <td class="tnum" style="text-align:right"><?= (int) $d['total'] ?></td>
                    <td class="tnum" style="text-align:right"><?= (int) $d['resolved'] ?></td>
                    <td class="tnum" style="text-align:right"><?= $rate ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div>
