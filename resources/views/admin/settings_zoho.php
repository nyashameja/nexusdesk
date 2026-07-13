<?php
/** @var bool $configured @var array|null $connection @var bool $connected */
?>
<div class="page-head"><h1>Settings</h1></div>
<?= \App\Core\View::render('partials.settings_tabs', ['tab' => 'zoho']) ?>

<div class="card" style="max-width:640px"><div class="card-body">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <h2 style="font-size:16px;margin:0">Zoho Books</h2>
        <?php if ($connected): ?>
            <span class="pill" style="background:var(--success-soft);color:var(--success)"><span class="d"></span>Connected</span>
        <?php else: ?>
            <span class="pill plain"><span class="d"></span>Not connected</span>
        <?php endif; ?>
    </div>
    <p class="muted" style="margin-top:0">NexusDesk reads invoices, quotes, statements and payments from Zoho Books —
        accounting stays in Zoho. Invoices display in the client portal, scoped to each company by its Zoho contact ID.</p>

    <?php if (!$configured): ?>
        <div class="alert alert-danger">Set <code>ZOHO_CLIENT_ID</code> and <code>ZOHO_CLIENT_SECRET</code> in <code>.env</code>,
            then reload this page to connect.</div>
    <?php elseif (!$connected): ?>
        <form method="post" action="/admin/settings/zoho/connect">
            <?= csrf_field() ?>
            <div class="field">
                <label for="organization_id">Zoho Books Organization ID</label>
                <input class="input" id="organization_id" name="organization_id" value="<?= e($connection['organization_id'] ?? '') ?>" required>
                <span class="hint">Find it in Zoho Books → Settings → Organizations.</span>
            </div>
            <button type="submit" class="btn btn-primary">Connect to Zoho Books</button>
        </form>
    <?php else: ?>
        <div class="kv"><span>Organization</span><b class="tnum"><?= e($connection['organization_id'] ?? '') ?></b></div>
        <div class="kv"><span>Last synced</span><b class="tnum"><?= e($connection['last_synced_at'] ?? 'never') ?></b></div>
        <div style="display:flex;gap:8px;margin-top:16px">
            <form method="post" action="/admin/settings/zoho/sync"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Sync now</button></form>
            <form method="post" action="/admin/settings/zoho/disconnect" data-confirm="Disconnect Zoho Books?"><?= csrf_field() ?><button class="btn btn-danger" type="submit">Disconnect</button></form>
        </div>
    <?php endif; ?>
</div></div>
