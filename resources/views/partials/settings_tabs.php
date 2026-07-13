<?php /** @var string $tab */
$tabs = [
    'branding'  => ['Branding', '/admin/settings/branding'],
    'general'   => ['General', '/admin/settings/general'],
    'mail'      => ['Email', '/admin/settings/mail'],
    'templates' => ['Templates', '/admin/settings/templates'],
    'zoho'      => ['Zoho Books', '/admin/settings/zoho'],
    'ai'        => ['AI', '/admin/settings/ai'],
    'api'       => ['API tokens', '/admin/settings/api'],
];
$current = $tab ?? '';
?>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px">
    <?php foreach ($tabs as $key => [$label, $href]): ?>
        <a class="btn btn-sm <?= $current === $key ? 'btn-soft' : 'btn-ghost' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>
