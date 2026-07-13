<?php /** @var array $departments */ ?>
<div class="page-head"><h1>Departments</h1></div>

<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Department</th><th>Email</th><th>Manager</th><th>Open tickets</th><th>Public</th></tr></thead>
        <tbody>
        <?php foreach ($departments as $d): ?>
            <tr>
                <td><strong><?= e($d['name']) ?></strong></td>
                <td class="muted"><?= e($d['email'] ?? '—') ?></td>
                <td><?= e(trim(($d['manager_first'] ?? '') . ' ' . ($d['manager_last'] ?? '')) ?: '—') ?></td>
                <td class="tnum"><?= (int) ($d['ticket_count'] ?? 0) ?></td>
                <td><?= $d['is_public'] ? '<span class="pill plain"><span class="d"></span>Yes</span>' : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
