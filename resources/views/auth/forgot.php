<h1>Reset your password</h1>
<p class="sub">Enter your email and we'll send you a reset link.</p>

<form method="post" action="/forgot-password" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" autofocus required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
</form>

<p class="sub" style="margin-top:22px;text-align:center"><a href="/login">← Back to sign in</a></p>
