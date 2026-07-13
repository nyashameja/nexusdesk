<?php
/** @var string $content */
$appName = 'NexusDesk';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error · <?= e($appName) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card" style="text-align:center">
        <?= $content ?>
    </div>
</div>
</body>
</html>
