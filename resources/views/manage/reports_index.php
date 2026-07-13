<?php /** @var array $summary @var int $days */ ?>
<div class="page-head"><h1>Reports</h1></div>

<div class="tiles" style="margin-bottom:20px">
    <div class="tile"><div class="k">Tickets</div><div class="v"><?= (int) $summary['total'] ?></div></div>
    <div class="tile"><div class="k">Resolved</div><div class="v" style="color:var(--success)"><?= (int) $summary['resolved'] ?></div></div>
    <div class="tile"><div class="k">SLA compliance</div><div class="v tnum"><?= e($summary['sla_compliance']) ?>%</div></div>
    <div class="tile"><div class="k">Avg resolution</div><div class="v tnum"><?= e($summary['avg_resolution_hours']) ?>h</div></div>
</div>

<div class="dept-grid" style="margin:0">
    <a class="dept" href="/manage/reports/volume"><strong>Ticket volume</strong><div class="muted" style="font-size:13px;margin-top:4px">Created vs resolved over time</div></a>
    <a class="dept" href="/manage/reports/sla"><strong>SLA compliance</strong><div class="muted" style="font-size:13px;margin-top:4px">Met vs breached, by department</div></a>
    <a class="dept" href="/manage/reports/agents"><strong>Agent performance</strong><div class="muted" style="font-size:13px;margin-top:4px">Volume, resolution time, CSAT</div></a>
    <a class="dept" href="/manage/reports/satisfaction"><strong>Customer satisfaction</strong><div class="muted" style="font-size:13px;margin-top:4px">Rating distribution</div></a>
</div>
