<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<string,mixed>|null $site */
/** @var array<int,string> $statuses */
/** @var array<int,string> $clients, $accounts */
$this->layout('layouts.app');

$isEdit = $site !== null;
$action = $isEdit ? url('/wordpress/' . (int) $site['id']) : url('/wordpress');
$val = static function (string $f) use ($site): string {
    $old = $_SESSION['_old'][$f] ?? null;
    return e($old ?? ($site[$f] ?? ''));
};
$sel = static function (string $f, $opt) use ($site): string {
    $current = $_SESSION['_old'][$f] ?? ($site[$f] ?? '');
    return (string) $current === (string) $opt ? 'selected' : '';
};
$label = static fn (string $s): string => ucfirst(str_replace('_', ' ', $s));
?>
<div class="pg-page-head">
    <div>
        <div class="flex items-center gap-2 mb-1"><a class="pg-soft" href="<?= e(url('/wordpress')) ?>" style="font-size:12.5px">← WordPress</a></div>
        <h1><?= $isEdit ? 'Edit site' : 'Add site' ?></h1>
    </div>
</div>

<div class="pg-card" style="max-width:820px">
    <div class="pg-card-body">
        <?php if ($isEdit): ?><div class="pg-alert info"><span>This record is <strong>manually maintained</strong>. The read-only WHM token cannot report WordPress details.</span></div><?php endif; ?>
        <form method="post" action="<?= e($action) ?>">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
            <div class="pg-grid cols-2">
                <div class="pg-field"><label>Site name *</label><input class="pg-input" name="name" value="<?= $val('name') ?>" required></div>
                <div class="pg-field"><label>URL</label><input class="pg-input" name="url" value="<?= $val('url') ?>" placeholder="https://"></div>
                <div class="pg-field"><label>Staging URL</label><input class="pg-input" name="staging_url" value="<?= $val('staging_url') ?>"></div>
                <div class="pg-field"><label>Status</label>
                    <select class="pg-input" name="wp_status">
                        <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $sel('wp_status', $s) ?>><?= e($label($s)) ?></option><?php endforeach; ?>
                    </select>
                </div>
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
                <div class="pg-field"><label>WordPress version</label><input class="pg-input" name="wp_version" value="<?= $val('wp_version') ?>"></div>
                <div class="pg-field"><label>PHP version</label><input class="pg-input" name="php_version" value="<?= $val('php_version') ?>"></div>
                <div class="pg-field"><label>Last backup date</label><input class="pg-input" type="date" name="last_backup_at" value="<?= $val('last_backup_at') ?>"></div>
                <div class="pg-field"><label>Last update date</label><input class="pg-input" type="date" name="last_update_at" value="<?= $val('last_update_at') ?>"></div>
                <div class="pg-field"><label>Maintenance plan</label><input class="pg-input" name="maintenance_plan" value="<?= $val('maintenance_plan') ?>"></div>
                <div class="pg-field"><label>Maintenance fee</label><input class="pg-input" name="maintenance_fee" value="<?= $val('maintenance_fee') ?>" placeholder="0.00"></div>
            </div>
            <div class="pg-field"><label>Notes</label><textarea class="pg-input" name="notes" rows="3"><?= $val('notes') ?></textarea></div>
            <div class="flex gap-2">
                <button class="pg-btn primary" type="submit"><?= $isEdit ? 'Save changes' : 'Add site' ?></button>
                <a class="pg-btn ghost" href="<?= e(url('/wordpress')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php unset($_SESSION['_old']); ?>
