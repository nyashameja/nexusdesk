<?php /** @var array $categories @var array $popular */ ?>
<div class="page-head" style="display:block">
    <h1 style="font-size:30px">Knowledge base</h1>
    <p class="muted">Guides and answers to help you get things done.</p>
</div>

<form action="/kb/search" method="get" class="card" style="margin-bottom:20px">
    <div class="card-body" style="display:flex;gap:10px">
        <input class="input" name="q" placeholder="Search articles…" aria-label="Search knowledge base">
        <button class="btn btn-primary" type="submit">Search</button>
    </div>
</form>

<div class="grid" style="grid-template-columns:2fr 1fr">
    <div>
        <?php if ($categories === []): ?>
            <div class="card"><div class="empty">No articles published yet.</div></div>
        <?php else: ?>
            <div class="dept-grid" style="margin:0">
                <?php foreach ($categories as $c): ?>
                    <a class="dept" href="/kb/c/<?= e($c['slug']) ?>">
                        <strong><?= e($c['name']) ?></strong>
                        <div class="muted" style="font-size:13px;margin-top:4px"><?= (int) $c['article_count'] ?> article<?= (int) $c['article_count'] === 1 ? '' : 's' ?></div>
                        <?php if (!empty($c['description'])): ?><div class="muted" style="font-size:12px;margin-top:6px"><?= e($c['description']) ?></div><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-head"><h2>Popular</h2></div>
        <div class="card-body">
            <?php if ($popular === []): ?>
                <p class="muted" style="margin:0">Nothing here yet.</p>
            <?php else: foreach ($popular as $a): ?>
                <div style="padding:8px 0;border-bottom:1px dashed var(--border)">
                    <a href="/kb/a/<?= e($a['slug']) ?>"><?= e($a['title']) ?></a>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
