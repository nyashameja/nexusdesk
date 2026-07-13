<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Core\Database;

/**
 * Database backups for shared hosting. Produces a gzipped SQL dump using PHP
 * (SHOW CREATE TABLE + batched INSERTs) so it works without shell access to
 * mysqldump. Backups live in storage/backups and are listed/downloaded/pruned
 * from the admin UI.
 */
final class BackupService
{
    public function __construct(
        private readonly Database $db,
        private readonly string $backupDir,
    ) {
    }

    public function create(): string
    {
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0750, true);
        }

        $filename = 'backup-' . date('Ymd-His') . '.sql.gz';
        $path = $this->backupDir . '/' . $filename;
        $handle = gzopen($path, 'wb9');
        if ($handle === false) {
            throw new \RuntimeException('Could not open backup file for writing.');
        }

        gzwrite($handle, "-- NexusDesk backup " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($this->tables() as $table) {
            $create = $this->db->selectOne("SHOW CREATE TABLE `$table`");
            $ddl = $create['Create Table'] ?? ($create['Create View'] ?? '');
            gzwrite($handle, "DROP TABLE IF EXISTS `$table`;\n$ddl;\n\n");

            $rows = $this->db->select("SELECT * FROM `$table`");
            foreach (array_chunk($rows, 100) as $chunk) {
                gzwrite($handle, $this->insertStatement($table, $chunk));
            }
            gzwrite($handle, "\n");
        }

        gzwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($handle);

        return $filename;
    }

    /** @return string[] */
    private function tables(): array
    {
        $rows = $this->db->select('SHOW TABLES');
        $tables = [];
        foreach ($rows as $row) {
            $tables[] = (string) reset($row);
        }
        return $tables;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function insertStatement(string $table, array $rows): string
    {
        if ($rows === []) {
            return '';
        }
        $pdo = $this->db->pdo();
        $columns = array_keys($rows[0]);
        $columnList = '`' . implode('`, `', $columns) . '`';

        $valueGroups = [];
        foreach ($rows as $row) {
            $values = array_map(
                static fn ($v): string => $v === null ? 'NULL' : $pdo->quote((string) $v),
                array_values($row)
            );
            $valueGroups[] = '(' . implode(', ', $values) . ')';
        }

        return "INSERT INTO `$table` ($columnList) VALUES\n" . implode(",\n", $valueGroups) . ";\n";
    }

    /** @return array<int,array{name:string,size:int,created:int}> */
    public function list(): array
    {
        if (!is_dir($this->backupDir)) {
            return [];
        }
        $backups = [];
        foreach (glob($this->backupDir . '/backup-*.sql.gz') ?: [] as $file) {
            $backups[] = [
                'name'    => basename($file),
                'size'    => (int) filesize($file),
                'created' => (int) filemtime($file),
            ];
        }
        usort($backups, static fn ($a, $b): int => $b['created'] <=> $a['created']);
        return $backups;
    }

    public function path(string $filename): ?string
    {
        // Prevent path traversal — only allow our backup filename pattern.
        if (!preg_match('/^backup-\d{8}-\d{6}\.sql\.gz$/', $filename)) {
            return null;
        }
        $path = $this->backupDir . '/' . $filename;
        return is_file($path) ? $path : null;
    }

    public function delete(string $filename): bool
    {
        $path = $this->path($filename);
        if ($path === null) {
            return false;
        }
        return @unlink($path);
    }

    public function prune(int $keep = 10): void
    {
        $backups = $this->list();
        foreach (array_slice($backups, $keep) as $old) {
            $this->delete($old['name']);
        }
    }
}
