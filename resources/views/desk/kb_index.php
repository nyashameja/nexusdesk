<?php /** @var array $articles @var int $total @var int $page @var int $perPage @var ?string $search */ ?>
<div class="page-head">
    <div><h1>Knowledge base</h1><div class="muted"><?= (int) $total ?> articles</div></div>
    <a class="btn btn-primary" href="/desk/kb/new">+ New article</a>
</div>

<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Visibility</th><th>Views</th><th></th></tr></thead>
        <tbody>
        <?php if ($articles === []): ?>
            <tr><td colspan="6"><div class="empty">No articles yet. <a href="/desk/kb/new">Write the first one →</a></div></td></tr>
        <?php else: foreach ($articles as $a): ?>
            <tr>
                <td><strong><?= e($a['title']) ?></strong></td>
                <td class="muted"><?= e($a['category_name']) ?></td>
                <td>
                    <?php $sc = $a['status'] === 'published' ? 'var(--success)' : ($a['status'] === 'draft' ? 'var(--warning)' : 'var(--muted)'); ?>
                    <span class="pill" style="background:color-mix(in srgb,<?= $sc ?> 15%,transparent);color:<?= $sc ?>"><span class="d"></span><?= e(ucfirst($a['status'])) ?></span>
                </td>
                <td class="muted"><?= $a['is_public'] ? 'Public' : 'Internal' ?></td>
                <td class="tnum"><?= (int) $a['views_count'] ?></td>
                <td style="text-align:right"><a class="btn btn-ghost btn-sm" href="/desk/kb/<?= (int) $a['id'] ?>/edit">Edit</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?= \App\Core\View::render('partials.pagination', ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'baseUrl' => '/desk/kb']) ?>
