<?php use App\Support\Security\Session; ?>
<h1>Welcome back</h1>
<p class="sub">Sign in to your <?= e(setting('general.app_name', 'NexusDesk')) ?> account.</p>

<form method="post" action="/login" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>"
               autocomplete="email" autofocus required>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <div class="field" style="flex-direction:row;align-items:center;justify-content:space-between">
        <label style="font-weight:500;display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="remember" value="1"> Remember me
        </label>
        <a href="/forgot-password" style="font-size:13px">Forgot password?</a>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Sign in</button>
</form>

<p class="sub" style="margin-top:22px;text-align:center">
    Need help? <a href="/submit">Submit a ticket</a> or <a href="/track">track an existing one</a>.
</p>
