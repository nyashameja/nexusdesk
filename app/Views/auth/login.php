<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var string|null $error */
/** @var bool $expired */
$this->layout('layouts.auth');
$title = 'Sign in';
?>
<div class="pg-auth-card">
    <div class="pg-auth-brand">
        <div class="pg-mark">P</div>
        <h1><?= e($appName ?? 'Paragon HostOps') ?></h1>
        <p><?= e($tagline ?? 'Hosting Management &amp; Operations Platform') ?></p>
    </div>

    <div class="pg-card">
        <div class="pg-card-body">
            <?php if (!empty($expired)): ?>
                <div class="pg-alert warn"><span>Your session expired. Please sign in again.</span></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="pg-alert error"><span><?= e($error) ?></span></div>
            <?php endif; ?>

            <form action="<?= e(url('/login')) ?>" method="post" autocomplete="off" novalidate>
                <?= csrf_field() ?>
                <div class="pg-field">
                    <label for="email">Email address</label>
                    <input class="pg-input" type="email" id="email" name="email" value="<?= old('email') ?>" required autofocus>
                </div>
                <div class="pg-field">
                    <label for="password">Password</label>
                    <input class="pg-input" type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="pg-btn primary" style="width:100%;justify-content:center">Sign in</button>
            </form>
        </div>
    </div>
    <p class="text-center mt-2" style="font-size:12px;color:#9fb0c6">Internal use only · The Paragon Design</p>
</div>
