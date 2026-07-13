<?php /** @var array $templates */ ?>
<div class="page-head"><h1>Settings</h1></div>
<?= \App\Core\View::render('partials.settings_tabs', ['tab' => 'templates']) ?>

<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Template</th><th>Subject</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($templates as $t): ?>
            <tr>
                <td><strong><?= e($t['name']) ?></strong><div class="muted tnum" style="font-size:12px"><?= e($t['slug']) ?></div></td>
                <td class="muted"><?= e($t['subject']) ?></td>
                <td><?= $t['is_active'] ? '<span class="pill" style="background:var(--success-soft);color:var(--success)"><span class="d"></span>Active</span>' : '<span class="pill plain"><span class="d"></span>Off</span>' ?></td>
                <td style="text-align:right"><a class="btn btn-ghost btn-sm" href="/admin/settings/templates/<?= (int) $t['id'] ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
