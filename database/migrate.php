<?php

declare(strict_types=1);

/**
 * Database migration runner (CLI).
 *
 * Usage:  php database/migrate.php [--seed]
 *
 * Applies the canonical schema (database/schema.sql) idempotently, then any
 * incremental files in database/migrations/*.sql that have not yet run. With
 * --seed it also runs the seeders in database/seeds.
 *
 * All statements use the configured PDO connection — no shell mysql client is
 * required, so this works over cPanel "Terminal" or a Cron Job.
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

$db  = new Database((array) Config::get('database', []));
$pdo = $db->pdo();

echo "Paragon HostOps — database migration\n";
echo "------------------------------------\n";

// Track applied migration files.
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        migration VARCHAR(190) NOT NULL,
        applied_at TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_migration (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

/** Execute a .sql file split on semicolons at line ends. */
$runSqlFile = static function (string $file) use ($pdo): void {
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Cannot read {$file}");
    }
    // Naive but sufficient splitter for our schema (no procedures/BEGIN..END).
    $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]/', $sql) ?: []));
    foreach ($statements as $statement) {
        if ($statement === '' || str_starts_with($statement, '--')) {
            continue;
        }
        $pdo->exec($statement);
    }
};

// 1. Canonical schema (idempotent via CREATE TABLE IF NOT EXISTS).
echo "Applying schema.sql ... ";
$runSqlFile(__DIR__ . '/schema.sql');
echo "done\n";

// 2. Incremental migrations.
$applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];
$applied = array_flip($applied);

foreach (glob(__DIR__ . '/migrations/*.sql') ?: [] as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        continue;
    }
    echo "Migrating {$name} ... ";
    $runSqlFile($file);
    $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration, applied_at) VALUES (?, UTC_TIMESTAMP())');
    $stmt->execute([$name]);
    echo "done\n";
}

// 3. Seeders.
if (in_array('--seed', $argv, true)) {
    echo "Seeding ...\n";
    require __DIR__ . '/seeds/RolesAndPermissionsSeeder.php';
    (new \ParagonHostOps\Database\Seeds\RolesAndPermissionsSeeder($db))->run();
    echo "Seeding complete.\n";
}

echo "\nAll migrations applied successfully.\n";
