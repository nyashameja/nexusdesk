<?php
$tz = setting('general.timezone', 'UTC');
$df = setting('general.date_format', 'Y-m-d');
$prefix = setting('general.ticket_prefix', 'NEXUS');
?>
<div class="page-head"><h1>Settings</h1></div>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px">
    <a class="btn btn-ghost btn-sm" href="/admin/settings/branding">Branding</a>
    <a class="btn btn-soft btn-sm" href="/admin/settings/general">General</a>
</div>

<div class="card" style="max-width:620px"><div class="card-body">
    <h2 style="font-size:16px;margin-bottom:12px">General</h2>
    <form method="post" action="/admin/settings/general">
        <?= csrf_field() ?>
        <div class="field">
            <label for="timezone">Timezone</label>
            <input class="input" id="timezone" name="timezone" value="<?= e($tz) ?>" required>
            <span class="hint">A PHP timezone identifier, e.g. UTC, Europe/London, Africa/Harare.</span>
        </div>
        <div class="row2">
            <div class="field">
                <label for="date_format">Date format</label>
                <input class="input" id="date_format" name="date_format" value="<?= e($df) ?>" required>
            </div>
            <div class="field">
                <label for="ticket_prefix">Ticket prefix</label>
                <input class="input" id="ticket_prefix" name="ticket_prefix" value="<?= e($prefix) ?>" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Save changes</button>
    </form>
</div></div>
