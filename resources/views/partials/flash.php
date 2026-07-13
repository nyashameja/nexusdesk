<?php
use App\Support\Security\Session;

$status = Session::getFlash('status');
$errors = Session::getFlash('errors');
?>
<?php if ($status): ?>
    <div class="alert alert-success"><?= e($status) ?></div>
<?php endif; ?>
<?php if (is_array($errors) && $errors !== []): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $fieldErrors): ?>
            <?php foreach ((array) $fieldErrors as $message): ?>
                <div><?= e($message) ?></div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
