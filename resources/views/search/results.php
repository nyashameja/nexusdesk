<?php /** @var string $query @var array $results @var int $count */ ?>
<div class="page-head" style="display:block"><h1>Search</h1></div>

<form action="/desk/search" method="get" class="card" style="margin-bottom:20px"><div class="card-body" style="display:flex;gap:10px">
    <input class="input" name="q" value="<?= e($query) ?>" placeholder="Tickets, companies, articles, people…" autofocus>
    <button class="btn btn-primary" type="submit">Search</button>
</div></form>

<?php if ($query === ''): ?>
    <p class="muted">Enter a search term above.</p>
<?php elseif ($count === 0): ?>
    <div class="card"><div class="empty">No results for "<?= e($query) ?>".</div></div>
<?php else: ?>
    <p class="muted"><?= (int) $count ?> result<?= $count === 1 ? '' : 's' ?> for "<?= e($query) ?>"</p>

    <?php
    $sections = [
        'tickets'   => ['Tickets', static fn ($r) => ['/desk/tickets/' . $r['id'], $r['reference'] . ' · ' . $r['subject']]],
        'companies' => ['Companies', static fn ($r) => ['/desk/companies/' . $r['id'], $r['name']]],
        'articles'  => ['Knowledge base', static fn ($r) => ['/kb/a/' . $r['slug'], $r['title']]],
        'users'     => ['People', static fn ($r) => ['/admin/users?q=' . urlencode($r['email']), trim($r['first_name'] . ' ' . $r['last_name']) . ' · ' . $r['email']]],
    ];
    foreach ($sections as $key => [$label, $fmt]):
        if (empty($results[$key])) { continue; }
    ?>
        <div class="card" style="margin-bottom:16px">
            <div class="card-head"><h2><?= $label ?></h2></div>
            <div class="card-body" style="padding:0">
                <?php foreach ($results[$key] as $r): [$href, $text] = $fmt($r); ?>
                    <a href="<?= e($href) ?>" style="display:block;padding:12px 18px;border-bottom:1px solid var(--border);color:var(--ink);text-decoration:none"><?= e($text) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
