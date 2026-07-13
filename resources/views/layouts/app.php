<?php
/** @var string $content */
$user = auth();
$appName = setting('general.app_name', $appName ?? 'NexusDesk');
$primary = setting('branding.primary_color', '#4f46e5');
$accent = setting('branding.accent_color', '#0891b2');
$active = $active ?? '';
$title = $title ?? $appName;
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
<div class="shell">
    <?= \App\Core\View::render('partials.sidebar', ['user' => $user, 'active' => $active, 'appName' => $appName]) ?>
    <div class="backdrop" aria-hidden="true"></div>
    <div class="content">
        <?= \App\Core\View::render('partials.topbar', ['user' => $user]) ?>
        <main class="page">
            <?= \App\Core\View::render('partials.flash') ?>
            <?= $content ?>
        </main>
    </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
