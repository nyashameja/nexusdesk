<?php
/** @var array|null $article @var array $categories */
$isNew = $article === null;
$action = $isNew ? '/desk/kb' : '/desk/kb/' . (int) $article['id'];
$val = static fn (string $k, $d = '') => $isNew ? old($k, $d) : ($article[$k] ?? $d);
?>
<div class="crumbs"><a href="/desk/kb">‹ Knowledge base</a></div>
<div class="page-head"><h1><?= $isNew ? 'New article' : 'Edit article' ?></h1></div>

<div class="card" style="max-width:820px"><div class="card-body">
<form method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <div class="field">
        <label for="title">Title</label>
        <input class="input" id="title" name="title" value="<?= e($val('title')) ?>" required>
    </div>
    <div class="row2">
        <div class="field">
            <label for="category_id">Category</label>
            <select class="select" id="category_id" name="category_id" required>
                <option value="">Select…</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $val('category_id') === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select class="select" id="status" name="status">
                <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= (string) $val('status', 'draft') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="field">
        <label for="body_html">Content (HTML allowed)</label>
        <textarea class="textarea" id="body_html" name="body_html" style="min-height:260px" required><?= e($val('body_html')) ?></textarea>
    </div>
    <div class="field">
        <label style="display:flex;gap:8px;align-items:center;font-weight:500">
            <input type="checkbox" name="is_public" value="1" <?= (int) $val('is_public', 1) === 1 ? 'checked' : '' ?>>
            Public (visible to customers and guests)
        </label>
    </div>
    <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create article' : 'Save changes' ?></button>
        <?php if (!$isNew): ?>
            <a class="btn btn-ghost" href="/kb/a/<?= e($article['slug']) ?>" target="_blank">Preview</a>
        <?php endif; ?>
    </div>
</form>
<?php if (!$isNew): ?>
    <form method="post" action="/desk/kb/<?= (int) $article['id'] ?>/delete" data-confirm="Delete this article?" style="margin-top:16px">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger btn-sm">Delete article</button>
    </form>
<?php endif; ?>
</div></div>
