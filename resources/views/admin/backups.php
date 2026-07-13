<?php /** @var array $backups */
$fmtSize = static fn (int $b): string => $b > 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024, 1) . ' KB';
?>
<div class="page-head">
    <div><h1>Backups</h1><div class="muted">Database dumps (gzipped SQL)</div></div>
    <form method="post" action="/admin/backups"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Create backup now</button></form>
</div>

<div class="alert alert-info">Schedule nightly backups with cron: <code>php /path/nexusdesk/cron/backup.php</code>. The 10 most recent are kept.</div>

<div class="table-wrap">
    <table class="data">
        <thead><tr><th>File</th><th>Size</th><th>Created</th><th></th></tr></thead>
        <tbody>
        <?php if ($backups === []): ?>
            <tr><td colspan="4"><div class="empty">No backups yet.</div></td></tr>
        <?php else: foreach ($backups as $b): ?>
            <tr>
                <td class="tnum"><?= e($b['name']) ?></td>
                <td class="tnum"><?= e($fmtSize((int) $b['size'])) ?></td>
                <td class="muted tnum" style="font-size:12px"><?= e(date('Y-m-d H:i', (int) $b['created'])) ?></td>
                <td style="text-align:right;white-space:nowrap">
                    <a class="btn btn-ghost btn-sm" href="/admin/backups/<?= e($b['name']) ?>/download">Download</a>
                    <form method="post" action="/admin/backups/<?= e($b['name']) ?>/delete" data-confirm="Delete this backup?" style="display:inline">
                        <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
