<?php /** @var array $article @var array $related */ ?>
<div class="crumbs">
    <a href="/kb">Knowledge base</a> / <a href="/kb/c/<?= e($article['category_slug']) ?>"><?= e($article['category_name']) ?></a>
</div>
<div class="page-head" style="display:block">
    <h1><?= e($article['title']) ?></h1>
    <div class="muted" style="font-size:13px;margin-top:6px">
        <?= e($article['author_name'] ?: 'Support team') ?>
        <?php if (!empty($article['published_at'])): ?> · <?= e(substr((string) $article['published_at'], 0, 10)) ?><?php endif; ?>
        · <?= (int) $article['views_count'] ?> views
    </div>
</div>

<div class="grid" style="grid-template-columns:2.2fr 1fr">
    <div class="card"><div class="card-body">
        <?php if (!empty($article['video_url'])): ?>
            <p><a class="btn btn-soft btn-sm" href="<?= e($article['video_url']) ?>" target="_blank" rel="noopener">▶ Watch video</a></p>
        <?php endif; ?>
        <div class="article-body"><?= $article['body_html'] ?></div>

        <hr style="border:0;border-top:1px solid var(--border);margin:24px 0">
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
            <span class="muted">Was this helpful?</span>
            <form method="post" action="/kb/a/<?= (int) $article['id'] ?>/feedback" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="helpful" value="1">
                <button class="btn btn-ghost btn-sm" type="submit">👍 Yes (<?= (int) $article['helpful_count'] ?>)</button>
            </form>
            <form method="post" action="/kb/a/<?= (int) $article['id'] ?>/feedback" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="helpful" value="0">
                <button class="btn btn-ghost btn-sm" type="submit">👎 No (<?= (int) $article['unhelpful_count'] ?>)</button>
            </form>
        </div>
    </div></div>

    <div>
        <?php if ($related !== []): ?>
            <div class="card"><div class="card-head"><h2>Related</h2></div><div class="card-body">
                <?php foreach ($related as $r): ?>
                    <div style="padding:7px 0;border-bottom:1px dashed var(--border)"><a href="/kb/a/<?= e($r['slug']) ?>"><?= e($r['title']) ?></a></div>
                <?php endforeach; ?>
            </div></div>
        <?php endif; ?>
        <div class="card"><div class="card-body" style="text-align:center">
            <p class="muted" style="margin-top:0">Didn't find what you need?</p>
            <a class="btn btn-primary btn-block" href="/submit">Contact support</a>
        </div></div>
    </div>
</div>
