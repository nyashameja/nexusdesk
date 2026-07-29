<?php

declare(strict_types=1);

/**
 * Web-based setup wizard.
 *
 * Lets an operator finish installation from the browser: it verifies the
 * environment, ensures the schema + roles are in place, and creates the first
 * Super Administrator.
 *
 * SECURITY: this page refuses to run once an administrator already exists (or
 * once storage/installed.lock is present). Delete it after setup regardless.
 */

use ParagonHostOps\Core\Config;
use ParagonHostOps\Core\Csrf;
use ParagonHostOps\Core\Database;
use ParagonHostOps\Core\Env;
use ParagonHostOps\Database\Seeds\RolesAndPermissionsSeeder;

$root = dirname(__DIR__);

require $root . '/bootstrap/autoload.php';
Env::load($root . '/.env');
Config::load($root . '/config');
date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$lockFile = $root . '/storage/installed.lock';
$errors   = [];
$success  = false;
$fatal    = null;

/** Minimal HTML page wrapper reusing the app stylesheet. */
$render = static function (string $inner) use ($root): void {
    $appName = e((string) Config::get('app.name', 'Paragon HostOps'));
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Install · ' . $appName . '</title>'
        . '<link rel="stylesheet" href="assets/css/app.css"></head>'
        . '<body><div class="pg-auth"><div class="pg-auth-card" style="max-width:520px">'
        . '<div class="pg-auth-brand"><div class="pg-mark">P</div><h1>' . $appName . '</h1>'
        . '<p>Installation wizard</p></div>'
        . '<div class="pg-card"><div class="pg-card-body">' . $inner . '</div></div>'
        . '<p class="text-center mt-2" style="font-size:12px;color:#9fb0c6">Delete this file after installation.</p>'
        . '</div></div></body></html>';
};

// --- Environment checks ---
$checks = [
    ['PHP 8.2+', PHP_VERSION_ID >= 80200, PHP_VERSION],
    ['pdo_mysql', extension_loaded('pdo_mysql'), ''],
    ['curl', extension_loaded('curl'), ''],
    ['openssl', extension_loaded('openssl'), ''],
    ['storage/ writable', is_writable($root . '/storage/logs') && is_writable($root . '/storage/sessions'), ''],
    ['.env present', is_readable($root . '/.env'), ''],
];
$envOk = array_reduce($checks, static fn (bool $c, array $r): bool => $c && $r[1], true);

// --- Database connection ---
$db = new Database((array) Config::get('database', []));
$dbOk = false;
$userCount = null;
try {
    $pdo = $db->pdo();
    $dbOk = true;
    $hasUsersTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn() !== false;
    if ($hasUsersTable) {
        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
} catch (Throwable $e) {
    $fatal = 'Database connection failed. Check the DB_* values in your .env, and that the '
        . 'database exists and the user has privileges (cPanel → MySQL Databases). Detail: '
        . e($e->getMessage());
}

// --- Already-installed gate ---
$alreadyInstalled = is_file($lockFile) || ($userCount !== null && $userCount > 0);

// --- Handle submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbOk && !$alreadyInstalled && $envOk) {
    if (!Csrf::validate((string) ($_POST['_csrf'] ?? ''))) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $name  = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $pass  = (string) ($_POST['password'] ?? '');
        $conf  = (string) ($_POST['password_confirm'] ?? '');

        if ($name === '') {
            $errors[] = 'Full name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (strlen($pass) < 12) {
            $errors[] = 'Password must be at least 12 characters.';
        }
        if ($pass !== $conf) {
            $errors[] = 'Passwords do not match.';
        }

        if ($errors === []) {
            try {
                // Ensure schema exists (idempotent) then seed roles/permissions.
                if (!$hasUsersTable) {
                    foreach (preg_split('/;\s*[\r\n]/', (string) file_get_contents($root . '/database/schema.sql')) ?: [] as $stmt) {
                        $stmt = trim($stmt);
                        if ($stmt !== '' && !str_starts_with($stmt, '--')) {
                            $pdo->exec($stmt);
                        }
                    }
                }

                require $root . '/database/seeds/RolesAndPermissionsSeeder.php';
                ob_start();
                (new RolesAndPermissionsSeeder($db))->run();
                ob_end_clean();

                // Create the administrator.
                $db->execute(
                    'INSERT INTO users (name, email, password_hash, is_active, created_at, updated_at)
                     VALUES (?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                    [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]
                );
                $userId = $db->lastInsertId();

                $roleId = (int) ($db->first('SELECT id FROM roles WHERE slug = ?', ['super_admin'])['id'] ?? 0);
                if ($roleId > 0) {
                    $db->execute('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $roleId]);
                }

                @file_put_contents($lockFile, gmdate('c') . " installed\n");
                $success = true;
            } catch (Throwable $e) {
                $errors[] = 'Setup failed: ' . e($e->getMessage());
            }
        }
    }
}

// --- Output ---
$rowsHtml = '';
foreach ($checks as [$label, $pass, $detail]) {
    $badge = $pass ? '<span class="pg-badge ok">OK</span>' : '<span class="pg-badge danger">Fail</span>';
    $rowsHtml .= '<tr><td>' . e($label) . ($detail ? ' <span class="pg-muted">(' . e($detail) . ')</span>' : '')
        . '</td><td class="text-right">' . $badge . '</td></tr>';
}

if ($success) {
    $loginUrl = e(rtrim((string) Config::get('app.url', ''), '/') . '/login');
    $render(
        '<div class="pg-alert success"><span><strong>Installation complete.</strong> Your administrator account has been created.</span></div>'
        . '<p class="pg-soft">For security, now <strong>delete these files</strong> from the server:</p>'
        . '<ul class="pg-soft" style="font-size:13px"><li><code>public/install.php</code></li><li><code>public/_diag.php</code></li><li><code>bin/create-admin.php</code></li></ul>'
        . '<a class="pg-btn primary" style="width:100%;justify-content:center" href="' . $loginUrl . '">Go to login</a>'
    );
    exit;
}

if ($alreadyInstalled) {
    $render(
        '<div class="pg-alert warn"><span><strong>Already installed.</strong> An administrator already exists, so the wizard is disabled.</span></div>'
        . '<p class="pg-soft">Delete <code>public/install.php</code> and <code>public/_diag.php</code>, then sign in.</p>'
        . '<a class="pg-btn primary" style="width:100%;justify-content:center" href="' . e(rtrim((string) Config::get('app.url', ''), '/') . '/login') . '">Go to login</a>'
    );
    exit;
}

$inner = '<h3 style="margin:0 0 12px;font-size:15px">Environment</h3>'
    . '<table class="pg-table" style="margin-bottom:18px"><tbody>' . $rowsHtml . '</tbody></table>';

if ($fatal !== null) {
    $inner .= '<div class="pg-alert error"><span>' . $fatal . '</span></div>';
    $render($inner);
    exit;
}
if (!$envOk) {
    $inner .= '<div class="pg-alert warn"><span>Resolve the failing checks above, then reload this page.</span></div>';
    $render($inner);
    exit;
}

foreach ($errors as $err) {
    $inner .= '<div class="pg-alert error"><span>' . $err . '</span></div>';
}

$inner .= '<h3 style="margin:6px 0 12px;font-size:15px">Create administrator</h3>'
    . '<form method="post" autocomplete="off">'
    . '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">'
    . '<div class="pg-field"><label>Full name</label><input class="pg-input" name="name" value="' . e((string) ($_POST['name'] ?? '')) . '" required></div>'
    . '<div class="pg-field"><label>Email address</label><input class="pg-input" type="email" name="email" value="' . e((string) ($_POST['email'] ?? '')) . '" required></div>'
    . '<div class="pg-field"><label>Password (min 12 characters)</label><input class="pg-input" type="password" name="password" required></div>'
    . '<div class="pg-field"><label>Confirm password</label><input class="pg-input" type="password" name="password_confirm" required></div>'
    . '<button class="pg-btn primary" type="submit" style="width:100%;justify-content:center">Create administrator</button>'
    . '</form>';

$render($inner);
