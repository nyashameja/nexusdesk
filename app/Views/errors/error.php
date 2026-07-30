<?php
/** @var int $status */
/** @var string $message */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= (int) $status ?> · <?= e($appName ?? 'Paragon HostOps') ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body>
<div class="pg-auth">
    <div class="pg-auth-card text-center">
        <div class="pg-auth-brand">
            <div class="pg-mark">P</div>
        </div>
        <div class="pg-card">
            <div class="pg-card-body">
                <h1 style="font-size:44px;margin:0;color:var(--pg-primary)"><?= (int) $status ?></h1>
                <p class="pg-soft mt-1"><?= e($message) ?></p>
                <a class="pg-btn primary mt-2" href="<?= e(url('/dashboard')) ?>">Return to dashboard</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
