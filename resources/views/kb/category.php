<?php /** @var array $category @var array $articles */ ?>
<div class="crumbs"><a href="/kb">Knowledge base</a> / <?= e($category['name']) ?></div>
<div class="page-head"><h1><?= e($category['name']) ?></h1></div>

<div class="card">
    <div class="card-body" style="padding:0">
        <?php if ($articles === []): ?>
            <div class="empty">No articles in this category yet.</div>
        <?php else: ?>
            <?php foreach ($articles as $a): ?>
                <a href="/kb/a/<?= e($a['slug']) ?>" style="display:block;padding:16px 18px;border-bottom:1px solid var(--border);color:inherit;text-decoration:none">
                    <strong style="color:var(--ink)"><?= e($a['title']) ?></strong>
                    <?php if (!empty($a['excerpt'])): ?><div class="muted" style="font-size:13px;margin-top:4px"><?= e($a['excerpt']) ?></div><?php endif; ?>
                    <div class="muted tnum" style="font-size:12px;margin-top:6px"><?= (int) $a['views_count'] ?> views · <?= (int) $a['helpful_count'] ?> found this helpful</div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
