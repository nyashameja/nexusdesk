<?php /** @var array<int,array<string,mixed>> $departments */ ?>
<section class="hero-lg">
    <div class="pill" style="background:var(--brand-soft);color:var(--brand);margin-bottom:14px">
        <span class="d"></span> Help desk &amp; client portal
    </div>
    <h1>Support that feels effortless for your clients.</h1>
    <p>Log a ticket, track its progress, browse the knowledge base, and manage your account — all in
       one modern portal.</p>
    <div class="cta-row">
        <a class="btn btn-primary" href="/submit">Submit a ticket</a>
        <a class="btn btn-ghost" href="/track">Track a ticket</a>
        <a class="btn btn-ghost" href="/kb">Browse knowledge base</a>
    </div>
</section>

<section style="margin-top:20px">
    <div class="card">
        <div class="card-head"><h2>How can we help?</h2></div>
        <div class="card-body">
            <p class="muted" style="margin-top:0">Choose the team that fits your request, or just submit a ticket and we'll route it.</p>
            <?php if ($departments === []): ?>
                <p class="muted">Departments will appear here once the system is configured.</p>
            <?php else: ?>
                <div class="dept-grid">
                    <?php foreach ($departments as $dept): ?>
                        <a class="dept" href="/submit?department=<?= (int) $dept['id'] ?>">
                            <strong><?= e($dept['name']) ?></strong>
                            <div class="muted" style="font-size:13px;margin-top:4px"><?= e($dept['email'] ?? '') ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
