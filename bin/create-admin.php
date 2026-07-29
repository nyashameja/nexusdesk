<?php

declare(strict_types=1);

/**
 * One-time administrator creation script (CLI).
 *
 * Usage:
 *   php bin/create-admin.php                 (interactive prompts)
 *   php bin/create-admin.php --name="Jane Doe" --email=jane@example.com
 *
 * The password is NEVER taken from the command line by default (it would leak
 * into shell history); it is read from a hidden prompt. No default password is
 * ever hard-coded. Delete or restrict this file after creating the first admin.
 */

require __DIR__ . '/../bootstrap/autoload.php';

use ParagonHostOps\Core\Config;
use ParagonHostOps\Core\Database;
use ParagonHostOps\Core\Env;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script may only be run from the command line.\n");
}

Env::load(__DIR__ . '/../.env');
Config::load(__DIR__ . '/../config');

$db = new Database((array) Config::get('database', []));

/** Parse --key=value CLI arguments. */
$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-zA-Z0-9_]+)=(.*)$/', $arg, $m)) {
        $args[$m[1]] = $m[2];
    }
}

$prompt = static function (string $label): string {
    echo $label;
    $line = fgets(STDIN);
    return $line === false ? '' : trim($line);
};

$promptHidden = static function (string $label): string {
    echo $label;
    if (function_exists('shell_exec') && stripos(PHP_OS, 'WIN') === false) {
        shell_exec('stty -echo 2>/dev/null');
        $line = fgets(STDIN);
        shell_exec('stty echo 2>/dev/null');
        echo "\n";
    } else {
        $line = fgets(STDIN);
    }
    return $line === false ? '' : trim($line);
};

echo "Paragon HostOps — create administrator\n";
echo "--------------------------------------\n";

$name  = $args['name']  ?? $prompt('Full name: ');
$email = $args['email'] ?? $prompt('Email address: ');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("A valid name and email are required.\n");
}

$existing = $db->first('SELECT id FROM users WHERE email = ?', [strtolower($email)]);
if ($existing !== null) {
    exit("A user with that email already exists (id {$existing['id']}).\n");
}

$password = $promptHidden('Password (min 12 chars): ');
$confirm  = $promptHidden('Confirm password: ');

if (strlen($password) < 12) {
    exit("Password must be at least 12 characters.\n");
}
if ($password !== $confirm) {
    exit("Passwords do not match.\n");
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$db->execute(
    'INSERT INTO users (name, email, password_hash, is_active, created_at, updated_at)
     VALUES (?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
    [$name, strtolower($email), $hash]
);
$userId = $db->lastInsertId();

// Assign the super_admin role (seed it if the seeder has not run).
$role = $db->first('SELECT id FROM roles WHERE slug = ?', ['super_admin']);
if ($role === null) {
    echo "Roles are not seeded yet. Run: php database/migrate.php --seed\n";
    echo "Then re-run this script, or the account will have no permissions.\n";
} else {
    $db->execute('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, (int) $role['id']]);
}

echo "\nAdministrator created (id {$userId}, {$email}).\n";
echo "IMPORTANT: delete or restrict bin/create-admin.php now that setup is complete.\n";
