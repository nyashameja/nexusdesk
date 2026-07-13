<?php
/** @var string $content */
$appName = setting('general.app_name', $appName ?? 'NexusDesk');
$primary = setting('branding.primary_color', '#4f46e5');
$accent = setting('branding.accent_color', '#0891b2');
$title = $title ?? 'Sign in';
?>
<!doctype html>
<html lang="en" style="--brand:<?= e($primary) ?>;--accent:<?= e($accent) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e($appName) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="logo"><span class="mark"></span><span class="brandname"><?= e($appName) ?></span></div>
        <?= \App\Core\View::render('partials.flash') ?>
        <?= $content ?>
    </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
