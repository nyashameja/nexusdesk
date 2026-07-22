<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<int,array<string,mixed>> $history */
/** @var array<int,array<int,array<string,mixed>>> $errors */
$this->layout('layouts.app');

$statusBadge = static function (string $status): string {
    return match ($status) {
        'completed' => '<span class="pg-badge ok">Completed</span>',
        'partial'   => '<span class="pg-badge warn">Partial</span>',
        'running'   => '<span class="pg-badge info">Running</span>',
        'failed'    => '<span class="pg-badge danger">Failed</span>',
        default     => '<span class="pg-badge neutral">' . e(ucfirst($status)) . '</span>',
    };
};
?>
<div class="pg-page-head">
    <div>
        <h1>Synchronisation History</h1>
        <p>Every run with record counts and safe error detail.</p>
    </div>
    <a class="pg-btn" href="<?= e(url('/sync')) ?>">Back to synchronisation</a>
</div>

<div class="pg-card">
    <div class="pg-table-wrap">
        <?php if (empty($history)): ?>
            <div class="pg-empty"><span class="ic">⟳</span><h3>No history</h3><p>No synchronisations have run yet.</p></div>
        <?php else: ?>
            <table class="pg-table">
                <thead><tr><th>#</th><th>Type</th><th>Status</th><th>Started</th><th>Finished</th><th>Processed</th><th>New</th><th>Updated</th><th>Failed</th><th>Message</th></tr></thead>
                <tbody>
                <?php foreach ($history as $run): $rid = (int) $run['id']; ?>
                    <tr>
                        <td class="pg-soft"><?= $rid ?></td>
                        <td><?= e(ucfirst((string) $run['sync_type'])) ?></td>
                        <td><?= $statusBadge((string) $run['status']) ?></td>
                        <td class="pg-soft"><?= $run['started_at'] ? e(date('d M H:i', strtotime((string) $run['started_at'] . ' UTC'))) : '—' ?></td>
                        <td class="pg-soft"><?= $run['finished_at'] ? e(date('d M H:i', strtotime((string) $run['finished_at'] . ' UTC'))) : '—' ?></td>
                        <td><?= (int) $run['records_processed'] ?></td>
                        <td><?= (int) $run['records_created'] ?></td>
                        <td><?= (int) $run['records_updated'] ?></td>
                        <td><?= (int) $run['records_failed'] ?></td>
                        <td class="pg-soft" style="max-width:260px"><?= e((string) ($run['message'] ?? '')) ?></td>
                    </tr>
                    <?php if (!empty($errors[$rid])): ?>
                        <tr><td colspan="10" style="background:#fdf5f5">
                            <strong class="pg-soft" style="font-size:12px">Errors:</strong>
                            <ul style="margin:6px 0 0;padding-left:18px">
                                <?php foreach ($errors[$rid] as $err): ?>
                                    <li style="font-size:12.5px"><code><?= e((string) $err['context']) ?></code> — <?= e((string) $err['message']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
