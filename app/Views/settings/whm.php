<?php
/** @var \ParagonHostOps\Core\View $this */
/** @var bool $configured */
/** @var string $host */
/** @var string $username */
/** @var bool $tokenPresent */
/** @var bool $mockMode */
$this->layout('layouts.app');
?>
<div class="pg-page-head">
    <div>
        <h1>WHM Settings</h1>
        <p>Read-only connection to the WHM/cPanel server. The API token is configured via the environment and is never displayed here.</p>
    </div>
</div>

<?php if ($mockMode): ?>
    <div class="pg-alert warn"><span><strong>Mock mode is enabled.</strong> The application is reading canned fixtures instead of the live WHM server. Disable <code>WHM_MOCK_MODE</code> in production.</span></div>
<?php endif; ?>

<div class="pg-grid cols-2 mb-3">
    <div class="pg-card">
        <div class="pg-card-head">Connection details</div>
        <div class="pg-card-body">
            <table class="pg-table">
                <tbody>
                    <tr><td class="pg-soft">Host</td><td><?= e($host) ?></td></tr>
                    <tr><td class="pg-soft">Port</td><td><?= e((string) config('whm.port')) ?></td></tr>
                    <tr><td class="pg-soft">Username</td><td><?= $username !== '' ? e($username) : '<span class="pg-muted">(not set)</span>' ?></td></tr>
                    <tr><td class="pg-soft">API token</td><td>
                        <?php if ($tokenPresent): ?>
                            <span class="pg-badge ok">Configured</span> <span class="pg-muted">(hidden for security)</span>
                        <?php else: ?>
                            <span class="pg-badge danger">Not configured</span>
                        <?php endif; ?>
                    </td></tr>
                    <tr><td class="pg-soft">SSL verification</td><td><?= config('whm.verify_ssl') ? '<span class="pg-badge ok">Enabled</span>' : '<span class="pg-badge warn">Disabled</span>' ?></td></tr>
                </tbody>
            </table>
            <p class="pg-soft mt-2 mb-0" style="font-size:12.5px">
                To change these values edit the <code>.env</code> file on the server. See the README for how to
                rotate or revoke the WHM API token.
            </p>
        </div>
    </div>

    <div class="pg-card">
        <div class="pg-card-head">Connection test</div>
        <div class="pg-card-body">
            <p class="pg-soft">Runs a read-only check: reachability, HTTPS, authentication, and a <code>listaccts</code> call. The token is never revealed.</p>
            <button class="pg-btn primary" data-action="whm-test" data-endpoint="<?= e(url('/api/whm/test-connection')) ?>" <?= $configured ? '' : 'disabled' ?>>
                Test WHM connection
            </button>
            <?php if (!$configured): ?>
                <p class="pg-muted mt-1" style="font-size:12px">Configure the WHM credentials before testing.</p>
            <?php endif; ?>
            <div class="mt-2" data-whm-test-result></div>
        </div>
    </div>
</div>

<div class="pg-card">
    <div class="pg-card-head">
        <span>Token capabilities</span>
        <button class="pg-btn" data-action="whm-capabilities" data-endpoint="<?= e(url('/api/whm/capabilities')) ?>" <?= $configured ? '' : 'disabled' ?>>Check capabilities</button>
    </div>
    <div class="pg-card-body">
        <p class="pg-soft">Probes which read-only WHM functions the current token is permitted to call. Nothing destructive is ever invoked.</p>
        <div class="mt-2" data-whm-capabilities-result>
            <div class="pg-empty"><span class="ic">⚙</span><h3>Not yet tested</h3><p>Run a capability check to see per-function availability.</p></div>
        </div>
    </div>
</div>
