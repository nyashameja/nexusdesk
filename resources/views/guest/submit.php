<?php
/** @var array<int,array<string,mixed>> $departments @var array<int,array<string,mixed>> $priorities */
$selectedDept = (int) ($_GET['department'] ?? old('department_id'));
?>
<h1 style="font-size:28px;margin-bottom:6px">Submit a ticket</h1>
<p class="muted" style="margin-bottom:22px">Tell us what's going on and we'll get back to you.</p>

<div class="card"><div class="card-body">
<form method="post" action="/submit" novalidate>
    <?= csrf_field() ?>
    <div class="row2">
        <div class="field">
            <label for="name">Your name</label>
            <input class="input" id="name" name="name" value="<?= e(old('name')) ?>" required>
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required>
        </div>
    </div>
    <div class="row2">
        <div class="field">
            <label for="department_id">Department</label>
            <select class="select" id="department_id" name="department_id" required>
                <option value="">Select…</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= $selectedDept === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="priority">Priority</label>
            <select class="select" id="priority" name="priority" required>
                <?php foreach ($priorities as $p): ?>
                    <option value="<?= e($p['slug']) ?>" <?= ($p['slug'] === 'medium') ? 'selected' : '' ?>><?= e($p['name']) ?></option>
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
