<?php
$fromName = setting('mail.from_name', 'NexusDesk Support');
$fromEmail = setting('mail.from_email', 'support@example.com');
?>
<div class="page-head"><h1>Settings</h1></div>
<?= \App\Core\View::render('partials.settings_tabs', ['tab' => 'mail']) ?>

<div class="card" style="max-width:620px;margin-bottom:16px"><div class="card-body">
    <h2 style="font-size:16px;margin-bottom:4px">Email identity</h2>
    <p class="muted" style="margin-top:0">The From name and address used on outgoing email. SMTP connection
        credentials are configured in <code>.env</code> for security.</p>
    <form method="post" action="/admin/settings/mail">
        <?= csrf_field() ?>
        <div class="row2">
            <div class="field"><label for="from_name">From name</label>
                <input class="input" id="from_name" name="from_name" value="<?= e($fromName) ?>" required></div>
            <div class="field"><label for="from_email">From email</label>
                <input class="input" type="email" id="from_email" name="from_email" value="<?= e($fromEmail) ?>" required></div>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div></div>

<div class="card" style="max-width:620px"><div class="card-body">
    <h2 style="font-size:16px;margin-bottom:4px">Test</h2>
    <p class="muted" style="margin-top:0">Send a test email to your own address to verify SMTP works.</p>
    <form method="post" action="/admin/settings/mail/test">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-ghost">Send test email</button>
    </form>
</div></div>
