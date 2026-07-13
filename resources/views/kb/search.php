<?php /** @var string $query @var array $results */ ?>
<div class="page-head" style="display:block">
    <h1 style="font-size:26px">Search</h1>
</div>
<form action="/kb/search" method="get" class="card" style="margin-bottom:20px"><div class="card-body" style="display:flex;gap:10px">
    <input class="input" name="q" value="<?= e($query) ?>" placeholder="Search articles…" autofocus>
    <button class="btn btn-primary" type="submit">Search</button>
</div></form>

<?php if ($query === ''): ?>
    <p class="muted">Type a query to search the knowledge base.</p>
<?php elseif ($results === []): ?>
    <div class="card"><div class="empty">No articles match "<?= e($query) ?>". <a href="/submit">Contact support →</a></div></div>
<?php else: ?>
    <p class="muted"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for "<?= e($query) ?>"</p>
    <div class="card"><div class="card-body" style="padding:0">
        <?php foreach ($results as $r): ?>
            <a href="/kb/a/<?= e($r['slug']) ?>" style="display:block;padding:14px 18px;border-bottom:1px solid var(--border);color:inherit;text-decoration:none">
                <strong style="color:var(--ink)"><?= e($r['title']) ?></strong>
                <?php if (!empty($r['excerpt'])): ?><div class="muted" style="font-size:13px;margin-top:4px"><?= e($r['excerpt']) ?></div><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div></div>
<?php endif; ?>
