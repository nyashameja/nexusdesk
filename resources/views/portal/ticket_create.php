<?php /** @var array $departments @var array $priorities */ ?>
<div class="crumbs"><a href="/portal/tickets">‹ My tickets</a></div>
<div class="page-head"><h1>New ticket</h1></div>

<div class="card" style="max-width:680px"><div class="card-body">
<form method="post" action="/portal/tickets" novalidate>
    <?= csrf_field() ?>
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
                    <option value="<?= e($p['slug']) ?>" <?= $p['slug'] === 'medium' ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="field">
        <label for="subject">Subject</label>
        <input class="input" id="subject" name="subject" value="<?= e(old('subject')) ?>" required>
    </div>
    <div class="field">
        <label for="message">Message</label>
        <textarea class="textarea" id="message" name="message" required><?= e(old('message')) ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Submit ticket</button>
</form>
</div></div>
