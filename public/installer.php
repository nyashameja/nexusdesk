<?php

declare(strict_types=1);

/**
 * NexusDesk installer.
 *
 * A self-contained wizard: requirements check → database connection →
 * schema + seed → admin account → SMTP → company/branding → finish. On success
 * it writes .env, seeds the admin, and drops storage/installed.lock so it
 * refuses to run again. Delete the lock (and .env) to re-run.
 */

$root = dirname(__DIR__);
$lock = $root . '/storage/installed.lock';

if (is_file($lock)) {
    http_response_code(403);
    exit('NexusDesk is already installed. Remove storage/installed.lock to re-run the installer.');
}

/** Requirement checks. */
function requirements(): array
{
    $writable = static fn (string $p): bool => is_writable($p);
    return [
        'PHP 8.3+'              => version_compare(PHP_VERSION, '8.3.0', '>='),
        'PDO MySQL'             => extension_loaded('pdo_mysql'),
        'mbstring'              => extension_loaded('mbstring'),
        'OpenSSL'              => extension_loaded('openssl'),
        'cURL'                  => extension_loaded('curl'),
        'JSON'                  => extension_loaded('json'),
        'storage/ writable'     => is_writable(dirname(__DIR__) . '/storage'),
    ];
}

$errors = [];
$done = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $db = [
        'host' => trim($_POST['db_host'] ?? 'localhost'),
        'port' => (int) ($_POST['db_port'] ?? 3306),
        'name' => trim($_POST['db_name'] ?? ''),
        'user' => trim($_POST['db_user'] ?? ''),
        'pass' => (string) ($_POST['db_pass'] ?? ''),
    ];
    $admin = [
        'first' => trim($_POST['admin_first'] ?? ''),
        'last'  => trim($_POST['admin_last'] ?? ''),
        'email' => trim($_POST['admin_email'] ?? ''),
        'pass'  => (string) ($_POST['admin_pass'] ?? ''),
    ];
    $company = trim($_POST['company'] ?? 'NexusDesk');

    if ($db['name'] === '' || $db['user'] === '') {
        $errors[] = 'Database name and username are required.';
    }
    if ($admin['email'] === '' || !filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid administrator email is required.';
    }
    if (strlen($admin['pass']) < 8) {
        $errors[] = 'Administrator password must be at least 8 characters.';
    }

    if ($errors === []) {
        try {
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // Fresh install: drop any existing tables first, so a previous
            // failed/partial run leaves a clean slate to re-install into.
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $existingTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($existingTables as $table) {
                $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $table) . '`');
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

            // Run schema then seed (multi-statement).
            foreach (['/database/schema.sql', '/database/seeds/seed.sql'] as $sqlFile) {
                $sql = file_get_contents($root . $sqlFile);
                if ($sql !== false && trim($sql) !== '') {
                    $pdo->exec($sql);
                }
            }

            // Replace the seeded placeholder admin with the real account.
            $hash = password_hash($admin['pass'], PASSWORD_DEFAULT, ['cost' => 12]);
            $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug='administrator'")->fetchColumn();
            $pdo->prepare('DELETE FROM users WHERE email = ?')->execute(['admin@example.com']);
            $pdo->prepare(
                'INSERT INTO users (role_id, first_name, last_name, email, password_hash, is_active, email_verified_at)
                 VALUES (?, ?, ?, ?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE first_name=VALUES(first_name), last_name=VALUES(last_name),
                    password_hash=VALUES(password_hash), role_id=VALUES(role_id), is_active=1'
            )->execute([$roleId, $admin['first'], $admin['last'], $admin['email'], $hash]);

            // Store the company name.
            $pdo->prepare(
                "INSERT INTO settings (group_name, key_name, value) VALUES ('general','app_name', ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)"
            )->execute([json_encode($company)]);

            // Write .env.
            $appKey = base64_encode(random_bytes(32));
            $env = strtr(file_get_contents($root . '/.env.example') ?: '', [
                'APP_NAME="NexusDesk"'          => 'APP_NAME="' . addslashes($company) . '"',
                'APP_ENV=production'            => 'APP_ENV=production',
                'APP_KEY='                      => 'APP_KEY=' . $appKey,
                'DB_HOST=localhost'             => 'DB_HOST=' . $db['host'],
                'DB_PORT=3306'                  => 'DB_PORT=' . $db['port'],
                'DB_DATABASE=nexusdesk'         => 'DB_DATABASE=' . $db['name'],
                'DB_USERNAME=root'              => 'DB_USERNAME=' . $db['user'],
                'DB_PASSWORD='                  => 'DB_PASSWORD=' . $db['pass'],
            ]);
            file_put_contents($root . '/.env', $env);

            // Lock the installer.
            file_put_contents($lock, date('c'));
            $done = true;
        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install NexusDesk</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card" style="max-width:560px">
        <div class="logo"><span class="mark"></span><span class="brandname">NexusDesk</span></div>

        <?php if ($done): ?>
            <h1>Installation complete 🎉</h1>
            <p class="sub">Your help desk is ready. For security, confirm <code>storage/installed.lock</code> exists.</p>
            <a class="btn btn-primary btn-block" href="/login">Go to sign in</a>
        <?php else: ?>
            <h1>Install NexusDesk</h1>
            <p class="sub">A few details and you're ready to go.</p>

            <?php if ($errors !== []): ?>
                <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?></div>
            <?php endif; ?>

            <div class="card" style="box-shadow:none;margin-bottom:16px"><div class="card-body" style="padding:14px">
                <div class="lab" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:700;margin-bottom:8px">Requirements</div>
                <?php foreach (requirements() as $label => $ok): ?>
                    <div class="kv"><span><?= htmlspecialchars($label) ?></span>
                        <span class="pill" style="background:<?= $ok ? 'var(--success-soft)' : 'var(--danger-soft)' ?>;color:<?= $ok ? 'var(--success)' : 'var(--danger)' ?>">
                            <span class="d"></span><?= $ok ? 'OK' : 'Missing' ?></span>
                    </div>
                <?php endforeach; ?>
            </div></div>

            <form method="post">
                <div class="lab" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:700;margin:6px 0 10px">Database</div>
                <div class="row2">
                    <div class="field"><label>Host</label><input class="input" name="db_host" value="localhost"></div>
                    <div class="field"><label>Port</label><input class="input" name="db_port" value="3306"></div>
                </div>
                <div class="field"><label>Database name</label><input class="input" name="db_name" required></div>
                <div class="row2">
                    <div class="field"><label>Username</label><input class="input" name="db_user" required></div>
                    <div class="field"><label>Password</label><input class="input" type="password" name="db_pass"></div>
                </div>

                <div class="lab" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:700;margin:12px 0 10px">Administrator</div>
                <div class="row2">
                    <div class="field"><label>First name</label><input class="input" name="admin_first" required></div>
                    <div class="field"><label>Last name</label><input class="input" name="admin_last" required></div>
                </div>
                <div class="field"><label>Email</label><input class="input" type="email" name="admin_email" required></div>
                <div class="field"><label>Password</label><input class="input" type="password" name="admin_pass" required></div>

                <div class="lab" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:700;margin:12px 0 10px">Company</div>
                <div class="field"><label>Company / brand name</label><input class="input" name="company" value="NexusDesk"></div>

                <button class="btn btn-primary btn-block" type="submit">Install NexusDesk</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
