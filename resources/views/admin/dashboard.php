<?php
/** @var array<string,int> $counts @var array<string,bool> $health */
$badge = static fn (bool $ok): string => $ok
    ? '<span class="pill" style="background:var(--success-soft);color:var(--success)"><span class="d"></span>OK</span>'
    : '<span class="pill" style="background:var(--danger-soft);color:var(--danger)"><span class="d"></span>Check</span>';
?>
<div class="page-head"><h1>Admin overview</h1></div>

<div class="tiles" style="margin-bottom:20px">
    <div class="tile"><div class="k">Open tickets</div><div class="v"><?= (int) ($counts['open'] ?? 0) ?></div></div>
    <div class="tile"><div class="k">Unassigned</div><div class="v" style="color:var(--warning)"><?= (int) ($counts['unassigned'] ?? 0) ?></div></div>
    <div class="tile"><div class="k">SLA at-risk</div><div class="v" style="color:var(--danger)"><?= (int) ($counts['atRisk'] ?? 0) ?></div></div>
    <div class="tile"><div class="k">Resolved today</div><div class="v" style="color:var(--success)"><?= (int) ($counts['resolvedToday'] ?? 0) ?></div></div>
</div>

<div class="card">
    <div class="card-head"><h2>System health</h2></div>
    <div class="card-body">
        <div class="kv"><span>Database</span><span><?= $badge($health['database']) ?></span></div>
        <div class="kv"><span>Mail configured</span><span><?= $badge($health['mail']) ?></span></div>
        <div class="kv"><span>Zoho Books</span><span><?= $badge($health['zoho']) ?></span></div>
        <div class="kv"><span>Storage writable</span><span><?= $badge($health['storage']) ?></span></div>
    </div>
</div>
