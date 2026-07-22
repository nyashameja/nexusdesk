<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<string,mixed>|null $client */
/** @var array<int,string> $types, $statuses */
$this->layout('layouts.app');

$isEdit = $client !== null;
$action = $isEdit ? url('/clients/' . (int) $client['id']) : url('/clients');
// Prefer old input (validation bounce), then the existing record.
$val = static function (string $field) use ($client): string {
    $old = $_SESSION['_old'][$field] ?? null;
    if ($old !== null) { return e($old); }
    return e($client[$field] ?? '');
};
$sel = static function (string $field, string $option) use ($client): string {
    $current = $_SESSION['_old'][$field] ?? ($client[$field] ?? '');
    return (string) $current === $option ? 'selected' : '';
};
?>
<div class="pg-page-head">
    <div>
        <div class="flex items-center gap-2 mb-1"><a class="pg-soft" href="<?= e(url('/clients')) ?>" style="font-size:12.5px">← Clients</a></div>
        <h1><?= $isEdit ? 'Edit client' : 'New client' ?></h1>
    </div>
</div>

<div class="pg-card" style="max-width:820px">
    <div class="pg-card-body">
        <form method="post" action="<?= e($action) ?>">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

            <div class="pg-grid cols-2">
                <div class="pg-field">
                    <label>Client type *</label>
                    <select class="pg-input" name="client_type" required>
                        <?php foreach ($types as $t): ?><option value="<?= e($t) ?>" <?= $sel('client_type', $t) ?>><?= e(ucfirst($t)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="pg-field">
                    <label>Status</label>
                    <select class="pg-input" name="status">
                        <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $sel('status', $s) ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="pg-field"><label>Company name</label><input class="pg-input" name="company_name" value="<?= $val('company_name') ?>"></div>
                <div class="pg-field"><label>Tax / registration number</label><input class="pg-input" name="tax_number" value="<?= $val('tax_number') ?>"></div>
                <div class="pg-field"><label>First name</label><input class="pg-input" name="first_name" value="<?= $val('first_name') ?>"></div>
                <div class="pg-field"><label>Last name</label><input class="pg-input" name="last_name" value="<?= $val('last_name') ?>"></div>
                <div class="pg-field"><label>Primary email</label><input class="pg-input" type="email" name="primary_email" value="<?= $val('primary_email') ?>"></div>
                <div class="pg-field"><label>Secondary email</label><input class="pg-input" type="email" name="secondary_email" value="<?= $val('secondary_email') ?>"></div>
                <div class="pg-field"><label>Phone</label><input class="pg-input" name="phone" value="<?= $val('phone') ?>"></div>
                <div class="pg-field"><label>WhatsApp</label><input class="pg-input" name="whatsapp" value="<?= $val('whatsapp') ?>"></div>
                <div class="pg-field"><label>Country</label><input class="pg-input" name="country" value="<?= $val('country') ?>"></div>
                <div class="pg-field"><label>Province</label><input class="pg-input" name="province" value="<?= $val('province') ?>"></div>
                <div class="pg-field"><label>City</label><input class="pg-input" name="city" value="<?= $val('city') ?>"></div>
                <div class="pg-field"><label>Billing address</label><input class="pg-input" name="billing_address" value="<?= $val('billing_address') ?>"></div>
            </div>
            <div class="pg-field"><label>Internal notes</label><textarea class="pg-input" name="notes" rows="3"><?= $val('notes') ?></textarea></div>

            <div class="flex gap-2">
                <button class="pg-btn primary" type="submit"><?= $isEdit ? 'Save changes' : 'Create client' ?></button>
                <a class="pg-btn ghost" href="<?= e($isEdit ? url('/clients/' . (int) $client['id']) : url('/clients')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php unset($_SESSION['_old']); ?>
