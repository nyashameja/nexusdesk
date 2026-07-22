<?php
/** Render one-shot flash messages set on the session. */
$session = app(\ParagonHostOps\Core\Session::class);
$flash = $session->pullAllFlash();
foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warn', 'info' => 'info'] as $key => $cls):
    if (empty($flash[$key])) { continue; }
    ?>
    <div class="pg-alert <?= $cls ?>"><span><?= e($flash[$key]) ?></span></div>
<?php endforeach; ?>
