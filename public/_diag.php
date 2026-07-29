<?php

/**
 * Standalone deployment diagnostic — NO framework, NO Composer required.
 *
 * Upload to public/ and visit https://your-domain/_diag.php
 * It reports the common deploy blockers (PHP version, vendor/, storage perms,
 * .env, database) WITHOUT printing any secret values.
 *
 * >>> DELETE THIS FILE as soon as you are done. <<<
 */

header('Content-Type: text/plain; charset=UTF-8');

$root = dirname(__DIR__);
$ok   = "OK   ";
$bad  = "FAIL ";
$warn = "WARN ";

echo "Paragon HostOps — deployment diagnostic\n";
echo "=======================================\n\n";

// 1. PHP version
$phpOk = PHP_VERSION_ID >= 80200;
echo ($phpOk ? $ok : $bad) . "PHP version: " . PHP_VERSION . ($phpOk ? "" : "  (need 8.2+ — set in cPanel MultiPHP Manager)") . "\n";

// 2. Required extensions
foreach (['pdo_mysql', 'curl', 'json', 'openssl', 'mbstring'] as $ext) {
    echo (extension_loaded($ext) ? $ok : $bad) . "ext {$ext}\n";
}

// 3. Composer dependencies
$hasVendor = is_file($root . '/vendor/autoload.php');
echo ($hasVendor ? $ok : $bad) . "vendor/autoload.php " . ($hasVendor ? "present" : "MISSING — run: composer install --no-dev --optimize-autoloader") . "\n";

// 4. Storage writable
foreach (['storage/logs', 'storage/sessions', 'storage/cache'] as $dir) {
    $p = $root . '/' . $dir;
    $w = is_dir($p) && is_writable($p);
    echo ($w ? $ok : $bad) . "{$dir} " . ($w ? "writable" : "NOT writable (chmod 0770)") . "\n";
}

// 5. .env present + parse DB settings (values never printed)
$envPath = $root . '/.env';
$env = [];
if (is_readable($envPath)) {
    echo $ok . ".env present and readable\n";
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\"'");
    }
} else {
    echo $bad . ".env MISSING or unreadable (copy .env.example to .env)\n";
}

// 6. Database connection (reports success/failure only — no credentials)
if ($hasVendor === false) {
    echo $warn . "Skipping DB test until Composer is installed.\n";
} elseif (!extension_loaded('pdo_mysql')) {
    echo $warn . "Skipping DB test — pdo_mysql not loaded.\n";
} else {
    $host = $env['DB_HOST'] ?? 'localhost';
    $port = $env['DB_PORT'] ?? '3306';
    $name = $env['DB_DATABASE'] ?? '';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';
    try {
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name}", $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo $ok . "Database connected. Tables found: " . count($tables) . "\n";
        if (!in_array('users', $tables, true)) {
            echo $warn . "  'users' table not found — run: php database/migrate.php --seed\n";
        }
    } catch (Throwable $e) {
        echo $bad . "Database connection failed: " . $e->getMessage() . "\n";
        echo "       (check DB_* values in .env; create the DB/user in cPanel > MySQL Databases)\n";
    }
}

echo "\nDone. >>> Now DELETE public/_diag.php <<<\n";
