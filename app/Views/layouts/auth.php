<?php
/** @var string $content */
$title = $title ?? 'Sign in';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e($appName ?? 'Paragon HostOps') ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body>
<div class="pg-auth">
    <?= $content ?>
</div>
</body>
</html>
