<?php
/** @var array $departments @var array $priorities @var array $companies */
?>
<div class="crumbs"><a href="/desk/tickets">‹ Tickets</a></div>
<div class="page-head"><h1>Raise a ticket on behalf of a client</h1></div>

<div class="card" style="max-width:720px"><div class="card-body">
<form method="post" action="/desk/tickets" novalidate>
    <?= csrf_field() ?>

    <div class="row2">
        <div class="field">
            <label for="client_email">Client email</label>
            <input class="input" type="email" id="client_email" name="client_email" value="<?= e(old('client_email')) ?>" required>
            <span class="hint">If they already have an account, the ticket links to it (and their company) automatically.</span>
        </div>
        <div class="field">
            <label for="client_name">Client name</label>
            <input class="input" id="client_name" name="client_name" value="<?= e(old('client_name')) ?>" required>
        </div>
    </div>

    <div class="field">
        <label for="company_id">Company <span class="muted" style="font-weight:400">(optional — used if the client isn't a registered account)</span></label>
        <select class="select" id="company_id" name="company_id">
            <option value="">— None / auto —</option>
            <?php foreach ($companies as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) old('company_id') === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="row2">
        <div class="field">
            <label for="department_id">Department</label>
            <select class="select" id="department_id" name="department_id" required>
                <option value="">Select…</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= (int) old('department_id') === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="priority">Priority</label>
            <select class="select" id="priority" name="priority" required>
                <?php foreach ($priorities as $p): ?>
                    <option value="<?= e($p['slug']) ?>" <?= (old('priority') ?: 'medium') === $p['slug'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="field">
        <label for="subject">Subject</label>
        <input class="input" id="subject" name="subject" value="<?= e(old('subject')) ?>" required>
    </div>
    <div class="field">
        <label for="message">Describe the issue (as reported by the client)</label>
        <textarea class="textarea" id="message" name="message" required><?= e(old('message')) ?></textarea>
    </div>

    <div class="field">
        <label style="display:flex;gap:8px;align-items:center;font-weight:500">
            <input type="checkbox" name="assign_to_me" value="1" checked> Assign to me
        </label>
    </div>

    <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary">Create ticket</button>
        <a class="btn btn-ghost" href="/desk/tickets">Cancel</a>
    </div>
    <p class="muted" style="font-size:12px;margin-top:14px">The client will receive the usual confirmation email and can track the ticket in their portal.</p>
</form>
</div></div>
