<h1 style="font-size:28px;margin-bottom:6px">Track a ticket</h1>
<p class="muted" style="margin-bottom:22px">Enter your ticket reference and the email you used.</p>

<div class="card" style="max-width:520px"><div class="card-body">
<form method="post" action="/track" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="reference">Ticket reference</label>
        <input class="input tnum" id="reference" name="reference" placeholder="NEXUS-1042"
               value="<?= e(old('reference') ?: ($_GET['reference'] ?? '')) ?>" required>
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required>
    </div>
    <button type="submit" class="btn btn-primary">Track ticket</button>
</form>
</div></div>
