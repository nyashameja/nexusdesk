<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<string,mixed>|null $domain */
/** @var array<int,string> $statuses */
/** @var array<int,string> $clients, $accounts */
$this->layout('layouts.app');

$isEdit = $domain !== null;
$action = $isEdit ? url('/domains/' . (int) $domain['id']) : url('/domains');
$val = static function (string $f) use ($domain): string {
    $old = $_SESSION['_old'][$f] ?? null;
    return e($old ?? ($domain[$f] ?? ''));
};
$sel = static function (string $f, $opt) use ($domain): string {
    $current = $_SESSION['_old'][$f] ?? ($domain[$f] ?? '');
    return (string) $current === (string) $opt ? 'selected' : '';
};
$checked = static function () use ($domain): string {
    $current = $_SESSION['_old']['auto_renew'] ?? ($domain['auto_renew'] ?? 0);
    return (int) $current === 1 ? 'checked' : '';
};
?>
<div class="pg-page-head">
    <div>
        <div class="flex items-center gap-2 mb-1"><a class="pg-soft" href="<?= e(url('/domains')) ?>" style="font-size:12.5px">← Domains</a></div>
        <h1><?= $isEdit ? 'Edit domain' : 'Add domain' ?></h1>
    </div>
</div>

<div class="pg-card" style="max-width:820px">
    <div class="pg-card-body">
        <form method="post" action="<?= e($action) ?>">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
            <div class="pg-grid cols-2">
                <div class="pg-field"><label>Domain name *</label><input class="pg-input" name="domain" value="<?= $val('domain') ?>" required></div>
                <div class="pg-field"><label>Registrar</label><input class="pg-input" name="registrar" value="<?= $val('registrar') ?>"></div>
                <div class="pg-field"><label>Linked client</label>
                    <select class="pg-input" name="client_id"><option value="">— None —</option>
                        <?php foreach ($clients as $id => $name): ?><option value="<?= (int) $id ?>" <?= $sel('client_id', $id) ?>><?= e($name) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="pg-field"><label>Linked hosting account</label>
                    <select class="pg-input" name="account_id"><option value="">— None —</option>
                        <?php foreach ($accounts as $id => $name): ?><option value="<?= (int) $id ?>" <?= $sel('account_id', $id) ?>><?= e($name) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="pg-field"><label>Registration date</label><input class="pg-input" type="date" name="registered_at" value="<?= $val('registered_at') ?>"></div>
                <div class="pg-field"><label>Expiry date</label><input class="pg-input" type="date" name="expires_at" value="<?= $val('expires_at') ?>"></div>
                <div class="pg-field"><label>Status</label>
                    <select class="pg-input" name="status">
                        <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $sel('status', $s) ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="pg-field"><label>Auto-renew</label>
                    <label style="font-weight:400;display:flex;align-items:center;gap:8px;margin-top:8px"><input type="checkbox" name="auto_renew" value="1" <?= $checked() ?>> Enabled</label>
                </div>
                <div class="pg-field"><label>Nameserver 1</label><input class="pg-input" name="nameserver1" value="<?= $val('nameserver1') ?>"></div>
                <div class="pg-field"><label>Nameserver 2</label><input class="pg-input" name="nameserver2" value="<?= $val('nameserver2') ?>"></div>
                <div class="pg-field"><label>Renewal cost</label><input class="pg-input" name="renewal_cost" value="<?= $val('renewal_cost') ?>" placeholder="0.00"></div>
                <div class="pg-field"><label>Client renewal price</label><input class="pg-input" name="client_price" value="<?= $val('client_price') ?>" placeholder="0.00"></div>
            </div>
            <div class="pg-field"><label>Notes</label><textarea class="pg-input" name="notes" rows="3"><?= $val('notes') ?></textarea></div>
            <div class="flex gap-2">
                <button class="pg-btn primary" type="submit"><?= $isEdit ? 'Save changes' : 'Add domain' ?></button>
                <a class="pg-btn ghost" href="<?= e($isEdit ? url('/domains/' . (int) $domain['id']) : url('/domains')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php unset($_SESSION['_old']); ?>
