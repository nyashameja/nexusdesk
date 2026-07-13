<?php
/** @var string $content */
$appName = setting('general.app_name', $appName ?? 'NexusDesk');
$primary = setting('branding.primary_color', '#4f46e5');
$accent = setting('branding.accent_color', '#0891b2');
$title = $title ?? $appName;
$user = auth();
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
<header class="guest-top">
    <a href="/" class="logo" style="display:flex;align-items:center;gap:10px">
        <span class="mark"></span><span class="brandname"><?= e($appName) ?></span>
    </a>
    <span style="flex:1"></span>
    <a class="btn btn-ghost btn-sm" href="/kb" style="margin-right:6px">Knowledge base</a>
    <a class="btn btn-ghost btn-sm" href="/track">Track ticket</a>
    <?php if ($user): ?>
        <a class="btn btn-primary btn-sm" href="<?= e($user->homePath()) ?>">Dashboard</a>
    <?php else: ?>
        <a class="btn btn-primary btn-sm" href="/login">Sign in</a>
    <?php endif; ?>
    <button class="icnbtn" data-theme-toggle title="Toggle theme" style="margin-left:6px">🌗</button>
</header>
<main class="guest-main">
    <?= \App\Core\View::render('partials.flash') ?>
    <?= $content ?>
</main>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
