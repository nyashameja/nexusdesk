<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<string,array{label:string,financial:bool}> $reports */
/** @var bool $canExport */
$this->layout('layouts.app');
?>
<div class="pg-page-head">
    <div>
        <h1>Reports</h1>
        <p>Export operational and financial reports as CSV. <?= $canExport ? '' : 'Your role can view this list but not export.' ?></p>
    </div>
</div>

<div class="pg-card">
    <div class="pg-table-wrap">
        <table class="pg-table">
            <thead><tr><th>Report</th><th>Type</th><th class="text-right">Export</th></tr></thead>
            <tbody>
            <?php foreach ($reports as $key => $r): ?>
                <tr>
                    <td><strong><?= e($r['label']) ?></strong></td>
                    <td><?= $r['financial'] ? '<span class="pg-badge info">Financial</span>' : '<span class="pg-badge neutral">Operational</span>' ?></td>
                    <td class="text-right">
                        <?php if ($canExport): ?>
                            <a class="pg-btn" href="<?= e(url('/reports/' . $key . '/export')) ?>">Download CSV</a>
                        <?php else: ?>
                            <span class="pg-muted" style="font-size:12px">No export permission</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="pg-muted mt-2" style="font-size:11.5px">CSV uses RFC 4180 escaping with formula-injection protection. PDF export is a planned future enhancement.</p>
