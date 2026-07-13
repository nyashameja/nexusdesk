<?php
/** @var array $recent */
$provider = setting('ai.provider', 'null');
?>
<div class="page-head"><h1>Settings</h1></div>
<?= \App\Core\View::render('partials.settings_tabs', ['tab' => 'ai']) ?>

<div class="card" style="max-width:640px;margin-bottom:16px"><div class="card-body">
    <h2 style="font-size:16px;margin-bottom:4px">AI provider</h2>
    <p class="muted" style="margin-top:0">AI assist (summarise, suggest reply, sentiment) is built into the ticket
        workspace. Choose a provider to activate it; set its API key in <code>.env</code>. Until then a safe
        stub responds so the workflow is fully testable.</p>
    <form method="post" action="/admin/settings/ai">
        <?= csrf_field() ?>
        <div class="field">
            <label for="provider">Provider</label>
            <select class="select" id="provider" name="provider">
                <?php foreach (['null' => 'Disabled (stub)', 'anthropic' => 'Anthropic (Claude)', 'openai' => 'OpenAI'] as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= (string) $provider === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select>
            <span class="hint">Selecting a real provider requires wiring its driver in the container binding (config/ai.php).</span>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div></div>

<div class="card" style="max-width:640px"><div class="card-body">
    <h2 style="font-size:16px;margin-bottom:8px">Recent AI activity</h2>
    <?php if ($recent === []): ?>
        <p class="muted" style="margin:0">No AI requests yet.</p>
    <?php else: foreach ($recent as $r): ?>
        <div class="kv"><span><?= e(ucfirst((string) $r['task'])) ?></span>
            <b class="tnum" style="font-size:12px"><?= e((string) $r['status']) ?> · <?= e(substr((string) $r['created_at'], 0, 16)) ?></b></div>
    <?php endforeach; endif; ?>
</div></div>
