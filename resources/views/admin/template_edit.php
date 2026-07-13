<?php /** @var array $template */ ?>
<div class="crumbs"><a href="/admin/settings/templates">‹ Templates</a></div>
<div class="page-head"><h1><?= e($template['name']) ?></h1></div>

<div class="card" style="max-width:820px"><div class="card-body">
    <p class="muted" style="margin-top:0">Use <code>{{ticket.reference}}</code>, <code>{{ticket.url}}</code>,
        <code>{{customer.first_name}}</code>, <code>{{reply.body}}</code> etc. — placeholders are filled at send time.</p>
    <form method="post" action="/admin/settings/templates/<?= (int) $template['id'] ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="subject">Subject</label>
            <input class="input" id="subject" name="subject" value="<?= e($template['subject']) ?>" required>
        </div>
        <div class="field">
            <label for="body_html">HTML body</label>
            <textarea class="textarea" id="body_html" name="body_html" style="min-height:220px" required><?= e($template['body_html']) ?></textarea>
        </div>
        <div class="field">
            <label for="body_text">Plain-text body (optional)</label>
            <textarea class="textarea" id="body_text" name="body_text" style="min-height:100px"><?= e($template['body_text'] ?? '') ?></textarea>
        </div>
        <div class="field">
            <label style="display:flex;gap:8px;align-items:center;font-weight:500">
                <input type="checkbox" name="is_active" value="1" <?= $template['is_active'] ? 'checked' : '' ?>> Active
            </label>
        </div>
        <button type="submit" class="btn btn-primary">Save template</button>
    </form>
</div></div>
