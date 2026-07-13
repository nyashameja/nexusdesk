<?php
/** @var int $page @var int $perPage @var int $total @var string $baseUrl */
$totalPages = (int) ceil(max(0, $total) / max(1, $perPage));
if ($totalPages <= 1) {
    return;
}
$sep = str_contains($baseUrl, '?') ? '&' : '?';
$link = static fn (int $p): string => e($baseUrl . $sep . 'page=' . $p);
?>
<nav class="pager" aria-label="Pagination">
    <?php if ($page > 1): ?>
        <a href="<?= $link($page - 1) ?>">‹ Prev</a>
    <?php else: ?>
        <span class="disabled">‹ Prev</span>
    <?php endif; ?>
    <span class="current"><?= $page ?> / <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
        <a href="<?= $link($page + 1) ?>">Next ›</a>
    <?php else: ?>
        <span class="disabled">Next ›</span>
    <?php endif; ?>
</nav>
