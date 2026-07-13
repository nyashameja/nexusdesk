<?php
/** @var array $tokens */
use App\Support\Security\Session;
$newToken = Session::getFlash('new_token');
?>
<div class="page-head"><h1>Settings</h1></div>
<?= \App\Core\View::render('partials.settings_tabs', ['tab' => 'api']) ?>

<?php if ($newToken): ?>
    <div class="alert alert-success">
        <strong>New token — copy it now, it won't be shown again:</strong>
        <div class="tnum" style="margin-top:6px;word-break:break-all;background:var(--surface);padding:8px 10px;border-radius:8px;border:1px solid var(--border)"><?= e($newToken) ?></div>
    </div>
<?php endif; ?>

<div class="card" style="max-width:640px;margin-bottom:16px"><div class="card-body">
    <h2 style="font-size:16px;margin-bottom:4px">Create a token</h2>
    <p class="muted" style="margin-top:0">Use tokens as <code>Authorization: Bearer &lt;token&gt;</code> against <code>/api/v1</code>.
        Tokens inherit your role's permissions.</p>
    <form method="post" action="/admin/settings/api" style="display:flex;gap:10px;align-items:end">
        <?= csrf_field() ?>
        <div class="field" style="margin:0;flex:1"><label for="name">Token name</label>
            <input class="input" id="name" name="name" placeholder="e.g. Zapier integration" required></div>
        <button type="submit" class="btn btn-primary">Generate</button>
    </form>
</div></div>

<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Name</th><th>Last used</th><th>Created</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if ($tokens === []): ?>
            <tr><td colspan="5"><div class="empty">No API tokens yet.</div></td></tr>
        <?php else: foreach ($tokens as $t): ?>
            <tr>
                <td><strong><?= e($t['name']) ?></strong></td>
                <td class="muted tnum" style="font-size:12px"><?= e($t['last_used_at'] ? substr((string) $t['last_used_at'], 0, 16) : 'never') ?></td>
                <td class="muted tnum" style="font-size:12px"><?= e(substr((string) $t['created_at'], 0, 10)) ?></td>
                <td>
                    <?php if ($t['revoked_at']): ?><span class="pill plain"><span class="d"></span>Revoked</span>
                    <?php else: ?><span class="pill" style="background:var(--success-soft);color:var(--success)"><span class="d"></span>Active</span><?php endif; ?>
                </td>
                <td style="text-align:right">
                    <?php if (!$t['revoked_at']): ?>
                        <form method="post" action="/admin/settings/api/<?= (int) $t['id'] ?>/revoke" data-confirm="Revoke this token?">
                            <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Revoke</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
