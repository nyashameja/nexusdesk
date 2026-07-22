<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<string,mixed>|null $sub */
/** @var array<int,string> $cycles, $statuses */
/** @var array<int,string> $clients, $accounts */
$this->layout('layouts.app');

$isEdit = $sub !== null;
$action = $isEdit ? url('/finance/subscriptions/' . (int) $sub['id']) : url('/finance/subscriptions');
$val = static function (string $f) use ($sub): string {
    $old = $_SESSION['_old'][$f] ?? null;
    return e($old ?? ($sub[$f] ?? ''));
};
$sel = static function (string $f, $opt) use ($sub): string {
    $current = $_SESSION['_old'][$f] ?? ($sub[$f] ?? '');
    return (string) $current === (string) $opt ? 'selected' : '';
};
?>
<div class="pg-page-head">
    <div>
        <div class="flex items-center gap-2 mb-1"><a class="pg-soft" href="<?= e(url('/finance/subscriptions')) ?>" style="font-size:12.5px">← Subscriptions</a></div>
        <h1><?= $isEdit ? 'Edit subscription' : 'New subscription' ?></h1>
    </div>
</div>

<div class="pg-card" style="max-width:820px">
    <div class="pg-card-body">
        <form method="post" action="<?= e($action) ?>">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
            <div class="pg-grid cols-2">
                <div class="pg-field"><label>Client *</label>
                    <select class="pg-input" name="client_id" required><option value="">— Select client —</option>
                        <?php foreach ($clients as $id => $name): ?><option value="<?= (int) $id ?>" <?= $sel('client_id', $id) ?>><?= e($name) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="pg-field"><label>Hosting account</label>
                    <select class="pg-input" name="account_id"><option value="">— None —</option>
                        <?php foreach ($accounts as $id => $name): ?><option value="<?= (int) $id ?>" <?= $sel('account_id', $id) ?>><?= e($name) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="pg-field"><label>Description</label><input class="pg-input" name="description" value="<?= $val('description') ?>" placeholder="e.g. Business hosting"></div>
                <div class="pg-field"><label>Billing cycle</label>
                    <select class="pg-input" name="billing_cycle">
                        <?php foreach ($cycles as $c): ?><option value="<?= e($c) ?>" <?= $sel('billing_cycle', $c) ?>><?= e(ucfirst($c)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="pg-field"><label>Price *</label><input class="pg-input" name="price" value="<?= $val('price') ?>" placeholder="0.00" required></div>
                <div class="pg-field"><label>Internal cost</label><input class="pg-input" name="internal_cost" value="<?= $val('internal_cost') ?>" placeholder="0.00"></div>
                <div class="pg-field"><label>Payment status</label>
                    <select class="pg-input" name="payment_status">
                        <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $sel('payment_status', $s) ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="pg-field"><label>Outstanding</label><input class="pg-input" name="outstanding" value="<?= $val('outstanding') ?>" placeholder="0.00"></div>
                <div class="pg-field"><label>Next billing date</label><input class="pg-input" type="date" name="next_billing_at" value="<?= $val('next_billing_at') ?>"></div>
                <div class="pg-field"><label>Last payment date</label><input class="pg-input" type="date" name="last_payment_at" value="<?= $val('last_payment_at') ?>"></div>
            </div>
            <div class="flex gap-2">
                <button class="pg-btn primary" type="submit"><?= $isEdit ? 'Save changes' : 'Create subscription' ?></button>
                <a class="pg-btn ghost" href="<?= e(url('/finance/subscriptions')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php unset($_SESSION['_old']); ?>
