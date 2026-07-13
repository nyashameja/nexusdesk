<?php
$appName = setting('general.app_name', 'NexusDesk');
$primary = setting('branding.primary_color', '#4f46e5');
$accent = setting('branding.accent_color', '#0891b2');
$theme = setting('branding.default_theme', 'system');
?>
<div class="page-head"><h1>Settings</h1></div>

<?= \App\Core\View::render('partials.settings_tabs', ['tab' => 'branding']) ?>

<div class="card" style="max-width:620px"><div class="card-body">
    <h2 style="font-size:16px;margin-bottom:4px">Branding</h2>
    <p class="muted" style="margin-top:0">These values re-theme the entire interface at runtime — no code change.</p>
    <form method="post" action="/admin/settings/branding">
        <?= csrf_field() ?>
        <div class="field">
            <label for="app_name">Application name</label>
            <input class="input" id="app_name" name="app_name" value="<?= e($appName) ?>" required>
        </div>
        <div class="row2">
            <div class="field">
                <label for="primary_color">Primary colour</label>
                <input class="input" id="primary_color" name="primary_color" type="text" value="<?= e($primary) ?>" required>
            </div>
            <div class="field">
                <label for="accent_color">Accent colour</label>
                <input class="input" id="accent_color" name="accent_color" type="text" value="<?= e($accent) ?>" required>
            </div>
        </div>
        <div class="field">
            <label for="default_theme">Default theme</label>
            <select class="select" id="default_theme" name="default_theme">
                <?php foreach (['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'] as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $theme === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;align-items:center;gap:12px;margin:10px 0 18px">
            <span class="muted" style="font-size:13px">Preview:</span>
            <span class="mark" style="--brand:<?= e($primary) ?>;--accent:<?= e($accent) ?>"></span>
            <strong><?= e($appName) ?></strong>
        </div>
        <button type="submit" class="btn btn-primary">Save changes</button>
    </form>
</div></div>
