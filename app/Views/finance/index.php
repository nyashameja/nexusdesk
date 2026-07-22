<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<string,float|int> $metrics */
/** @var array<string,mixed> $charts */
$this->layout('layouts.app');
$m = $metrics;
$money = static fn ($v): string => number_format((float) $v, 2);
?>
<div class="pg-page-head">
    <div>
        <h1>Finance</h1>
        <p>Recurring revenue and billing overview. Amounts are in the configured base currency.</p>
    </div>
    <a class="pg-btn" href="<?= e(url('/finance/subscriptions')) ?>">Manage subscriptions</a>
</div>

<div class="pg-grid cols-4 mb-3">
    <div class="pg-stat"><div class="label">Monthly recurring revenue</div><div class="value"><?= $money($m['mrr']) ?></div><div class="sub">MRR</div></div>
    <div class="pg-stat"><div class="label">Annual recurring revenue</div><div class="value"><?= $money($m['arr']) ?></div><div class="sub">ARR</div></div>
    <div class="pg-stat"><div class="label">Est. gross profit / mo</div><div class="value"><?= $money($m['gross_profit']) ?></div><div class="sub">MRR − internal cost</div></div>
    <div class="pg-stat"><div class="label">Outstanding</div><div class="value" style="color:var(--pg-danger)"><?= $money($m['outstanding']) ?></div><div class="sub">Across subscriptions</div></div>
</div>

<div class="pg-grid cols-4 mb-3">
    <div class="pg-stat"><div class="label">Renewals this month</div><div class="value"><?= (int) $m['renewals_this'] ?></div></div>
    <div class="pg-stat"><div class="label">Renewals next month</div><div class="value"><?= (int) $m['renewals_next'] ?></div></div>
    <div class="pg-stat"><div class="label">Active paying clients</div><div class="value" style="color:var(--pg-success)"><?= (int) $m['active_clients'] ?></div></div>
    <div class="pg-stat"><div class="label">Overdue clients</div><div class="value" style="color:var(--pg-warning)"><?= (int) $m['overdue_clients'] ?></div></div>
</div>

<div class="pg-grid cols-2">
    <div class="pg-card">
        <div class="pg-card-head">Monthly revenue by category</div>
        <div class="pg-card-body"><div style="height:240px"><canvas id="chartRevenue"></canvas></div></div>
    </div>
    <div class="pg-card">
        <div class="pg-card-head">Subscriptions by payment status</div>
        <div class="pg-card-body"><div style="height:240px"><canvas id="chartPayments"></canvas></div></div>
    </div>
</div>

<script type="application/json" id="pg-finance-data"><?= json_encode($charts, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
