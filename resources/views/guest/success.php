<?php /** @var string $reference */ ?>
<div class="card" style="max-width:560px;margin:40px auto;text-align:center">
    <div class="card-body" style="padding:36px">
        <div style="font-size:44px">✅</div>
        <h1 style="font-size:24px;margin:12px 0 6px">Ticket submitted</h1>
        <p class="muted">Your reference number is</p>
        <p class="ref tnum" style="font-size:24px;margin:8px 0 20px"><?= e($reference) ?></p>
        <p class="muted" style="margin-bottom:22px">We've emailed a confirmation. Keep your reference to track progress.</p>
        <a class="btn btn-primary" href="/track?reference=<?= e($reference) ?>">Track this ticket</a>
        <a class="btn btn-ghost" href="/">Back home</a>
    </div>
</div>
