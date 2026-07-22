<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<string,mixed> $client */
/** @var \ParagonHostOps\Repositories\ClientRepository $repo */
/** @var array<int,array<string,mixed>> $accounts, $domains, $notes, $unlinked */
/** @var array{monthly:float,annual:float,outstanding:float} $financials */
/** @var \ParagonHostOps\Services\Auth $auth */
$this->layout('layouts.app');

$c = $client;
$canManage = $auth->can('clients.manage');
$canFinance = $auth->can('finance.view');
$row = static fn (string $l, ?string $v): string => '<tr><td class="pg-soft" style="width:40%">' . e($l) . '</td><td>' . ($v !== null && $v !== '' ? e($v) : '<span class="pg-muted">—</span>') . '</td></tr>';
?>
<div class="pg-page-head">
    <div>
        <div class="flex items-center gap-2 mb-1"><a class="pg-soft" href="<?= e(url('/clients')) ?>" style="font-size:12.5px">← Clients</a></div>
        <h1><?= e($repo->displayName($c)) ?></h1>
        <p><?= e(ucfirst((string) $c['client_type'])) ?> · <span class="pg-badge <?= $c['status'] === 'active' ? 'ok' : 'neutral' ?>"><?= e(ucfirst((string) $c['status'])) ?></span></p>
    </div>
    <?php if ($canManage): ?>
        <a class="pg-btn" href="<?= e(url('/clients/' . (int) $c['id'] . '/edit')) ?>">Edit client</a>
    <?php endif; ?>
</div>

<div class="pg-grid cols-2 mb-3">
    <div class="pg-card">
        <div class="pg-card-head">Client details</div>
        <div class="pg-table-wrap"><table class="pg-table"><tbody>
            <?= $row('Primary email', $c['primary_email'] ?? null) ?>
            <?= $row('Secondary email', $c['secondary_email'] ?? null) ?>
            <?= $row('Phone', $c['phone'] ?? null) ?>
            <?= $row('WhatsApp', $c['whatsapp'] ?? null) ?>
            <?= $row('Location', trim(implode(', ', array_filter([$c['city'] ?? '', $c['province'] ?? '', $c['country'] ?? ''])))) ?>
            <?= $row('Billing address', $c['billing_address'] ?? null) ?>
            <?= $row('Tax number', $c['tax_number'] ?? null) ?>
            <?= $row('Date added', !empty($c['created_at']) ? date('d M Y', strtotime((string) $c['created_at'] . ' UTC')) : null) ?>
        </tbody></table></div>
    </div>

    <div class="pg-card">
        <div class="pg-card-head">Account summary</div>
        <div class="pg-card-body">
            <div class="pg-grid cols-3" style="gap:12px">
                <div class="pg-stat" style="box-shadow:none;border:1px solid var(--pg-border)"><div class="label">Hosting accounts</div><div class="value" style="font-size:22px"><?= count($accounts) ?></div></div>
                <div class="pg-stat" style="box-shadow:none;border:1px solid var(--pg-border)"><div class="label">Domains</div><div class="value" style="font-size:22px"><?= count($domains) ?></div></div>
                <?php if ($canFinance): ?>
                    <div class="pg-stat" style="box-shadow:none;border:1px solid var(--pg-border)"><div class="label">MRR</div><div class="value" style="font-size:22px"><?= number_format($financials['monthly'], 0) ?></div></div>
                <?php endif; ?>
            </div>
            <?php if ($canFinance): ?>
                <table class="pg-table mt-2"><tbody>
                    <?= $row('Monthly value', number_format($financials['monthly'], 2)) ?>
                    <?= $row('Annual value', number_format($financials['annual'], 2)) ?>
                    <?= $row('Outstanding', number_format($financials['outstanding'], 2)) ?>
                </tbody></table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="pg-card mb-3">
    <div class="pg-card-head">Linked hosting accounts</div>
    <div class="pg-table-wrap">
        <?php if (empty($accounts)): ?>
            <div class="pg-empty" style="padding:26px"><span class="ic">▤</span><h3>No linked accounts</h3><p>Link a WHM account below.</p></div>
        <?php else: ?>
            <table class="pg-table">
                <thead><tr><th>Domain</th><th>Username</th><th>Package</th><th>Status</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($accounts as $a): ?>
                    <tr>
                        <td><a href="<?= e(url('/accounts/' . (int) $a['id'])) ?>"><?= e($a['domain']) ?></a></td>
                        <td class="pg-soft"><?= e($a['username']) ?></td>
                        <td class="pg-soft"><?= e($a['package'] ?? '—') ?></td>
                        <td><?= (int) $a['suspended'] === 1 ? '<span class="pg-badge danger">Suspended</span>' : '<span class="pg-badge ok">Active</span>' ?></td>
                        <?php if ($canManage): ?>
                            <td class="text-right">
                                <form method="post" action="<?= e(url('/clients/' . (int) $c['id'] . '/accounts/' . (int) $a['id'] . '/unlink')) ?>" style="margin:0">
                                    <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
                                    <button class="pg-btn ghost" type="submit" style="padding:4px 10px">Unlink</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php if ($canManage): ?>
        <div class="pg-card-body" style="border-top:1px solid var(--pg-border)">
            <form method="post" action="<?= e(url('/clients/' . (int) $c['id'] . '/link-account')) ?>" class="flex gap-2 items-center flex-wrap">
                <?= csrf_field() ?>
                <span class="pg-soft" style="font-size:12.5px">Link account:</span>
                <select class="pg-input" name="account_id" style="max-width:340px" required>
                    <option value="">Select an unlinked account…</option>
                    <?php foreach ($unlinked as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= e($u['domain']) ?> (<?= e($u['username']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button class="pg-btn primary" type="submit">Link</button>
                <span class="pg-muted" style="font-size:11.5px">Administrator confirmation required — no automatic matching is saved.</span>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="pg-grid cols-2">
    <div class="pg-card">
        <div class="pg-card-head">Linked domains</div>
        <div class="pg-table-wrap">
            <?php if (empty($domains)): ?>
                <div class="pg-empty" style="padding:26px"><span class="ic">◈</span><h3>No domains</h3><p>Add domains in the Domains module.</p></div>
            <?php else: ?>
                <table class="pg-table"><thead><tr><th>Domain</th><th>Status</th><th>Expires</th></tr></thead><tbody>
                <?php foreach ($domains as $d): ?>
                    <tr><td><a href="<?= e(url('/domains/' . (int) $d['id'])) ?>"><?= e($d['domain']) ?></a></td>
                    <td class="pg-soft"><?= e(ucfirst(str_replace('_', ' ', (string) $d['status']))) ?></td>
                    <td class="pg-soft"><?= !empty($d['expires_at']) ? e(date('d M Y', strtotime((string) $d['expires_at']))) : '—' ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
    </div>

    <div class="pg-card">
        <div class="pg-card-head">Notes &amp; activity</div>
        <div class="pg-card-body">
            <?php if ($canManage): ?>
                <form method="post" action="<?= e(url('/clients/' . (int) $c['id'] . '/notes')) ?>" class="mb-2">
                    <?= csrf_field() ?>
                    <textarea class="pg-input" name="body" rows="2" placeholder="Add an internal note…"></textarea>
                    <div class="mt-1"><button class="pg-btn primary" type="submit" style="padding:6px 12px">Add note</button></div>
                </form>
            <?php endif; ?>
            <?php if (!empty($c['notes'])): ?>
                <div class="pg-alert info" style="white-space:pre-wrap"><span><?= e($c['notes']) ?></span></div>
            <?php endif; ?>
            <?php if (empty($notes)): ?>
                <p class="pg-muted" style="font-size:12.5px">No activity notes yet.</p>
            <?php else: ?>
                <?php foreach ($notes as $n): ?>
                    <div style="border-bottom:1px solid var(--pg-border);padding:8px 0">
                        <div style="font-size:13px;white-space:pre-wrap"><?= e($n['body']) ?></div>
                        <div class="pg-muted" style="font-size:11px"><?= e($n['author'] ?? 'System') ?> · <?= e(date('d M Y H:i', strtotime((string) $n['created_at'] . ' UTC'))) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
