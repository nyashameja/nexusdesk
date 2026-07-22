<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var array<int,array<string,mixed>> $accounts */
/** @var array<string,string> $filters */
/** @var string $sort */
/** @var string $dir */
/** @var int $page, $pages, $total, $perPage */
/** @var array<int,string> $packages */
$this->layout('layouts.app');

$pctBar = static function ($used, $limit): array {
    $used = (float) $used; $limit = (float) $limit;
    $p = $limit > 0 ? (int) min(100, round(($used / $limit) * 100)) : 0;
    $cls = $p >= 90 ? 'danger' : ($p >= 80 ? 'warn' : '');
    return [$p, $cls];
};
$mb = static fn ($v): string => (float) $v >= 1024 ? number_format((float) $v / 1024, 1) . 'G' : (int) $v . 'M';

// Build a URL preserving current filters, overriding given keys.
$query = static function (array $override) use ($filters, $sort, $dir): string {
    $params = array_filter(array_merge($filters, ['sort' => $sort, 'dir' => $dir], $override), static fn ($v) => $v !== '' && $v !== null);
    return url('/accounts' . ($params ? '?' . http_build_query($params) : ''));
};
// Sortable column header link (toggles direction).
$sortLink = static function (string $key, string $label) use ($sort, $dir, $query): string {
    $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    $arrow = $sort === $key ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="' . e($query(['sort' => $key, 'dir' => $nextDir, 'page' => '1'])) . '" style="color:inherit">' . e($label) . $arrow . '</a>';
};
$hasFilters = trim(implode('', $filters)) !== '';
?>
<div class="pg-page-head">
    <div>
        <h1>Hosting Accounts</h1>
        <p><?= (int) $total ?> account<?= $total === 1 ? '' : 's' ?> in cache. Data is read-only in Version&nbsp;1.</p>
    </div>
</div>

<!-- Filter panel -->
<div class="pg-card mb-3">
    <div class="pg-card-body">
        <form method="get" action="<?= e(url('/accounts')) ?>" class="pg-grid" style="grid-template-columns:repeat(4,1fr);gap:12px;align-items:end">
            <div class="pg-field" style="margin:0;grid-column:span 2">
                <label>Search</label>
                <input class="pg-input" type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Domain, username or email">
            </div>
            <div class="pg-field" style="margin:0">
                <label>Package</label>
                <select class="pg-input" name="package">
                    <option value="">All packages</option>
                    <?php foreach ($packages as $p): ?>
                        <option value="<?= e($p) ?>" <?= $filters['package'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pg-field" style="margin:0">
                <label>Status</label>
                <select class="pg-input" name="status">
                    <option value="">All</option>
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="suspended" <?= $filters['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>
            <div class="pg-field" style="margin:0">
                <label>Disk</label>
                <select class="pg-input" name="disk">
                    <option value="">Any</option>
                    <option value="high" <?= $filters['disk'] === 'high' ? 'selected' : '' ?>>&ge; 80% used</option>
                </select>
            </div>
            <div class="pg-field" style="margin:0">
                <label>Bandwidth</label>
                <select class="pg-input" name="bandwidth">
                    <option value="">Any</option>
                    <option value="high" <?= $filters['bandwidth'] === 'high' ? 'selected' : '' ?>>&ge; 80% used</option>
                </select>
            </div>
            <div class="pg-field" style="margin:0">
                <label>SSL</label>
                <select class="pg-input" name="ssl">
                    <option value="">Any</option>
                    <?php foreach (['valid' => 'Valid', 'expiring' => 'Expiring', 'expired' => 'Expired', 'missing' => 'Missing', 'attention' => 'Needs attention'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $filters['ssl'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pg-field" style="margin:0">
                <label>Client link</label>
                <select class="pg-input" name="linked">
                    <option value="">Any</option>
                    <option value="linked" <?= $filters['linked'] === 'linked' ? 'selected' : '' ?>>Linked</option>
                    <option value="unlinked" <?= $filters['linked'] === 'unlinked' ? 'selected' : '' ?>>Unlinked</option>
                </select>
            </div>
            <div class="flex gap-2" style="grid-column:span 2">
                <button class="pg-btn primary" type="submit">Apply filters</button>
                <?php if ($hasFilters): ?><a class="pg-btn ghost" href="<?= e(url('/accounts')) ?>">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="pg-card">
    <div class="pg-table-wrap">
        <?php if (empty($accounts)): ?>
            <div class="pg-empty">
                <span class="ic">▤</span>
                <h3>No accounts found</h3>
                <p><?= $hasFilters ? 'No accounts match the current filters.' : 'Run a synchronisation to populate cached account data.' ?></p>
            </div>
        <?php else: ?>
            <table class="pg-table">
                <thead>
                    <tr>
                        <th><?= $sortLink('domain', 'Domain') ?></th>
                        <th>Username</th>
                        <th><?= $sortLink('package', 'Package') ?></th>
                        <th>Owner</th>
                        <th><?= $sortLink('disk', 'Disk') ?></th>
                        <th><?= $sortLink('bandwidth', 'Bandwidth') ?></th>
                        <th><?= $sortLink('status', 'Status') ?></th>
                        <th>SSL</th>
                        <th>Client</th>
                        <th><?= $sortLink('created', 'Created') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($accounts as $a):
                    [$dp, $dcls] = $pctBar($a['disk_used_mb'] ?? 0, $a['disk_limit_mb'] ?? 0);
                    [$bp, $bcls] = $pctBar($a['bandwidth_used_mb'] ?? 0, $a['bandwidth_limit_mb'] ?? 0);
                    $clientName = trim((string) ($a['company_name'] ?? '') ?: trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')));
                ?>
                    <tr>
                        <td><a href="<?= e(url('/accounts/' . (int) $a['id'])) ?>"><strong><?= e($a['domain'] ?? '') ?></strong></a></td>
                        <td class="pg-soft"><?= e($a['username'] ?? '') ?></td>
                        <td class="pg-soft"><?= e($a['package'] ?? '—') ?></td>
                        <td class="pg-soft"><?= e($a['owner'] ?? '—') ?></td>
                        <td style="min-width:110px">
                            <div class="pg-progress" title="<?= $dp ?>%"><span class="<?= $dcls ?>" style="width:<?= $dp ?>%"></span></div>
                            <span class="pg-muted" style="font-size:11px"><?= $mb($a['disk_used_mb'] ?? 0) ?> / <?= (float) ($a['disk_limit_mb'] ?? 0) > 0 ? $mb($a['disk_limit_mb']) : '∞' ?></span>
                        </td>
                        <td style="min-width:110px">
                            <div class="pg-progress" title="<?= $bp ?>%"><span class="<?= $bcls ?>" style="width:<?= $bp ?>%"></span></div>
                            <span class="pg-muted" style="font-size:11px"><?= $mb($a['bandwidth_used_mb'] ?? 0) ?> / <?= (float) ($a['bandwidth_limit_mb'] ?? 0) > 0 ? $mb($a['bandwidth_limit_mb']) : '∞' ?></span>
                        </td>
                        <td>
                            <?php if ((int) ($a['suspended'] ?? 0) === 1): ?>
                                <span class="pg-badge danger">Suspended</span>
                            <?php else: ?>
                                <span class="pg-badge ok">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $s = (string) ($a['ssl_status'] ?? 'unknown');
                            $sc = match ($s) { 'valid' => 'ok', 'expiring' => 'warn', 'expired', 'invalid', 'missing' => 'danger', default => 'neutral' }; ?>
                            <span class="pg-badge <?= $sc ?>"><?= e(ucfirst($s)) ?></span>
                        </td>
                        <td>
                            <?php if ($clientName !== ''): ?>
                                <span class="pg-soft"><?= e($clientName) ?></span>
                            <?php else: ?>
                                <span class="pg-badge neutral">Unlinked</span>
                            <?php endif; ?>
                        </td>
                        <td class="pg-soft"><?= !empty($a['whm_created_at']) ? e(date('d M Y', strtotime((string) $a['whm_created_at']))) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php if ($pages > 1): ?>
    <div class="flex gap-2 mt-2 items-center justify-between">
        <span class="pg-soft" style="font-size:12.5px">Page <?= $page ?> of <?= $pages ?> · <?= (int) $total ?> total</span>
        <div class="flex gap-2">
            <?php if ($page > 1): ?><a class="pg-btn" href="<?= e($query(['page' => (string) ($page - 1)])) ?>">Previous</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="pg-btn" href="<?= e($query(['page' => (string) ($page + 1)])) ?>">Next</a><?php endif; ?>
        </div>
    </div>
<?php endif; ?>
