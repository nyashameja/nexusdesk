<?php
/** @var array $users @var int $total @var int $page @var int $perPage @var array $roles @var ?string $search @var ?string $role */
?>
<div class="page-head">
    <div><h1>Users</h1><div class="muted"><?= (int) $total ?> total</div></div>
</div>

<div class="card" style="margin-bottom:16px"><div class="card-body" style="padding:14px 16px">
    <form method="get" action="/admin/users" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:0">
        <div class="field" style="margin:0;flex:1;min-width:200px">
            <label>Search</label>
            <input class="input" name="q" value="<?= e($search ?? '') ?>" placeholder="Name or email">
        </div>
        <div class="field" style="margin:0">
            <label>Role</label>
            <select class="select" name="role">
                <option value="">All roles</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r['slug']) ?>" <?= ($role === $r['slug']) ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Filter</button>
    </form>
</div></div>

<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th></tr></thead>
        <tbody>
        <?php if ($users === []): ?>
            <tr><td colspan="5"><div class="empty">No users found.</div></td></tr>
        <?php else: foreach ($users as $u): ?>
            <tr>
                <td><strong><?= e(trim($u['first_name'] . ' ' . $u['last_name'])) ?></strong></td>
                <td class="muted"><?= e($u['email']) ?></td>
                <td><span class="pill plain"><span class="d"></span><?= e($u['role_name']) ?></span></td>
                <td>
                    <?php if ($u['is_active']): ?>
                        <span class="pill" style="background:var(--success-soft);color:var(--success)"><span class="d"></span>Active</span>
                    <?php else: ?>
                        <span class="pill" style="background:var(--danger-soft);color:var(--danger)"><span class="d"></span>Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="muted tnum" style="font-size:12px"><?= e($u['last_login_at'] ? substr((string) $u['last_login_at'], 0, 16) : '—') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?= \App\Core\View::render('partials.pagination', ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'baseUrl' => '/admin/users']) ?>
