<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<string,mixed>|null $monitor */
/** @var array<int,string> $clients, $accounts */
$this->layout('layouts.app');

$isEdit = $monitor !== null;
$action = $isEdit ? url('/uptime/' . (int) $monitor['id']) : url('/uptime');
$val = static function (string $f, $default = '') use ($monitor): string {
    $old = $_SESSION['_old'][$f] ?? null;
    return e($old ?? ($monitor[$f] ?? $default));
};
$sel = static function (string $f, $opt) use ($monitor): string {
    $current = $_SESSION['_old'][$f] ?? ($monitor[$f] ?? '');
    return (string) $current === (string) $opt ? 'selected' : '';
};
$checked = static function () use ($monitor): string {
    $current = $_SESSION['_old']['enabled'] ?? ($monitor['enabled'] ?? 1);
    return (int) $current === 1 ? 'checked' : '';
};
?>
<div class="pg-page-head">
    <div>
        <div class="flex items-center gap-2 mb-1"><a class="pg-soft" href="<?= e(url('/uptime')) ?>" style="font-size:12.5px">← Uptime</a></div>
        <h1><?= $isEdit ? 'Edit monitor' : 'Add monitor' ?></h1>
    </div>
</div>

<div class="pg-card" style="max-width:720px">
    <div class="pg-card-body">
        <form method="post" action="<?= e($action) ?>">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
            <div class="pg-field"><label>Label *</label><input class="pg-input" name="label" value="<?= $val('label') ?>" required></div>
            <div class="pg-field"><label>URL *</label><input class="pg-input" name="url" value="<?= $val('url') ?>" placeholder="https://example.com" required></div>
            <div class="pg-grid cols-2">
                <div class="pg-field"><label>Expected status code</label><input class="pg-input" name="expected_status" value="<?= $val('expected_status', '200') ?>"></div>
                <div class="pg-field"><label>Enabled</label><label style="font-weight:400;display:flex;align-items:center;gap:8px;margin-top:8px"><input type="checkbox" name="enabled" value="1" <?= $checked() ?>> Actively monitor</label></div>
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
            </div>
            <div class="flex gap-2">
                <button class="pg-btn primary" type="submit"><?= $isEdit ? 'Save changes' : 'Add monitor' ?></button>
                <a class="pg-btn ghost" href="<?= e(url('/uptime')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php unset($_SESSION['_old']); ?>
