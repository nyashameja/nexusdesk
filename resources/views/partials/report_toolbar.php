<?php /** @var string $report @var int $days @var string $path */ ?>
<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:18px">
    <form method="get" style="display:flex;gap:6px;margin:0">
        <select class="select" name="days" onchange="this.form.submit()" style="width:auto">
            <?php foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last year'] as $d => $lbl): ?>
                <option value="<?= $d ?>" <?= (int) $days === $d ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <span style="flex:1"></span>
    <?php if (in_array($report, ['volume', 'agents', 'departments'], true)): ?>
        <span class="muted" style="font-size:13px">Export:</span>
        <a class="btn btn-ghost btn-sm" href="/manage/reports/<?= e($report) ?>/export?format=csv&days=<?= (int) $days ?>">CSV</a>
        <a class="btn btn-ghost btn-sm" href="/manage/reports/<?= e($report) ?>/export?format=xlsx&days=<?= (int) $days ?>">Excel</a>
        <a class="btn btn-ghost btn-sm" href="/manage/reports/<?= e($report) ?>/export?format=pdf&days=<?= (int) $days ?>">PDF</a>
    <?php endif; ?>
</div>
