<?php
/** @var string $content */
/** @var \ParagonHostOps\Services\Auth $auth */
$title = $title ?? 'Overview';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?> · <?= e($appName ?? 'Paragon HostOps') ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body>
<div class="pg-shell">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="pg-backdrop"></div>

    <div class="pg-main">
        <?php include __DIR__ . '/../partials/topbar.php'; ?>

        <main class="pg-content">
            <?php include __DIR__ . '/../partials/flash.php'; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?php foreach (($scripts ?? []) as $script): ?>
<script src="<?= e(url($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
