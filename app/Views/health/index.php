<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<int,array{account:array<string,mixed>,result:array<string,mixed>}> $scored */
/** @var array<string,int> $bands */
/** @var int $total */
/** @var \ParagonHostOps\Services\Auth $auth */
use ParagonHostOps\Services\HealthScoreService;
$this->layout('layouts.app');

$bandClass = static fn (string $b): string => match ($b) {
    'excellent' => 'ok', 'good' => 'ok', 'attention' => 'info', 'risk' => 'warn', 'critical' => 'danger', default => 'neutral',
};
$factorLabel = static fn (string $f): string => ucfirst(str_replace('_', ' ', $f));
?>
<div class="pg-page-head">
    <div>
        <h1>Client Health</h1>
        <p>Transparent 0–100 scoring across <?= (int) $total ?> account(s). Scores use only the data available; sparse data is flagged, never guessed.</p>
    </div>
    <?php if ($auth->can('sync.run') || $auth->can('clients.manage')): ?>
        <form method="post" action="<?= e(url('/health/recompute')) ?>" style="margin:0">
            <?= csrf_field() ?>
            <button class="pg-btn primary" type="submit">Recompute &amp; store</button>
        </form>
    <?php endif; ?>
</div>

<div class="pg-grid cols-4 mb-3">
    <div class="pg-stat"><div class="label">Excellent / Good</div><div class="value" style="color:var(--pg-success)"><?= (int) $bands['excellent'] + (int) $bands['good'] ?></div></div>
    <div class="pg-stat"><div class="label">Needs attention</div><div class="value" style="color:var(--pg-info)"><?= (int) $bands['attention'] ?></div></div>
    <div class="pg-stat"><div class="label">At risk</div><div class="value" style="color:var(--pg-warning)"><?= (int) $bands['risk'] ?></div></div>
    <div class="pg-stat"><div class="label">Critical</div><div class="value" style="color:var(--pg-danger)"><?= (int) $bands['critical'] ?></div></div>
</div>

<div class="pg-card">
    <div class="pg-table-wrap">
        <?php if (empty($scored)): ?>
            <div class="pg-empty"><span class="ic">♥</span><h3>No accounts to score</h3><p>Run a synchronisation to populate account data first.</p></div>
        <?php else: ?>
            <table class="pg-table">
                <thead><tr><th>Domain</th><th>Score</th><th>Band</th><th>Key factors</th></tr></thead>
                <tbody>
                <?php foreach ($scored as $item):
                    $a = $item['account']; $r = $item['result'];
                    $band = (string) $r['band'];
                    // Show the factors that reduced the score most.
                    $negative = array_filter($r['factors'], static fn ($f) => $f['impact'] < 0);
                    usort($negative, static fn ($x, $y) => $x['impact'] <=> $y['impact']);
                    $top = array_slice($negative, 0, 3);
                ?>
                    <tr>
                        <td><a href="<?= e(url('/accounts/' . (int) $a['id'])) ?>"><strong><?= e($a['domain']) ?></strong></a></td>
                        <td>
                            <?php if ($r['score'] === null): ?>
                                <span class="pg-muted">—</span>
                            <?php else: ?>
                                <strong style="font-size:15px"><?= (int) $r['score'] ?></strong><span class="pg-muted">/100</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="pg-badge <?= $bandClass($band) ?>"><?= e(HealthScoreService::bandLabel($band)) ?></span></td>
                        <td>
                            <?php if ($r['incomplete']): ?>
                                <span class="pg-soft" style="font-size:12px">Health score incomplete because some data is unavailable.</span>
                            <?php elseif (empty($top)): ?>
                                <span class="pg-soft" style="font-size:12px">No negative factors — all clear.</span>
                            <?php else: ?>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($top as $f): ?>
                                        <span class="pg-badge warn" title="<?= e($f['detail']) ?>"><?= e($factorLabel($f['factor'])) ?> <?= (int) $f['impact'] ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<p class="pg-muted mt-2" style="font-size:11.5px">Scoring weights: account status 25 · SSL 20 · disk 15 · domain expiry 15 · payment 15 · bandwidth 10. The final score is the weighted average over factors with available data.</p>
