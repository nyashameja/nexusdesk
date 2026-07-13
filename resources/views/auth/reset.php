<?php /** @var string $token @var string $email */ ?>
<h1>Choose a new password</h1>
<p class="sub">Enter a new password for your account.</p>

<form method="post" action="/reset-password" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="field">
        <label for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>" required>
    </div>
    <div class="field">
        <label for="password">New password</label>
        <input class="input" type="password" id="password" name="password" autocomplete="new-password" required>
        <span class="hint">At least 8 characters.</span>
    </div>
    <div class="field">
        <label for="password_confirmation">Confirm password</label>
        <input class="input" type="password" id="password_confirmation" name="password_confirmation" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Reset password</button>
</form>
