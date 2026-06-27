<?php

declare(strict_types=1);

namespace App\Services\System;

use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Dashboard-driven database & file backups (Setup Bible: "Backup" / "Restore").
 *
 * Pure PHP, no terminal: SQL dumps are produced by reading the schema and rows
 * directly through the existing PDO connection (app('db')), so the host needs
 * neither mysqldump nor any other CLI tool. File backups use the bundled
 * ZipArchive extension. Everything lands under storage/backups.
 *
 * Every path that crosses the user boundary (download / delete / restore) is
 * resolved with basename() and re-checked against the backups directory, so a
 * crafted "name" can never escape via traversal.
 */
final class BackupManager
{
    /** Rows per INSERT statement — keeps individual queries a sane size. */
    private const INSERT_BATCH = 200;

    /**
     * Dump the current database to storage/backups/db-<timestamp>.sql and
     * return the absolute path to the file.
     *
     * The dump is self-contained: foreign-key checks are disabled for the
     * duration, every base table is dropped and re-created from its own
     * SHOW CREATE TABLE, and all rows are written as batched INSERTs with
     * values quoted via PDO::quote (numbers/NULL written raw).
     */
    public function createDatabaseBackup(): string
    {
        $dir = $this->dir();
        $path = $dir . '/db-' . $this->timestamp() . '.sql';

        $handle = @fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Could not open backup file for writing.');
        }

        try {
            $pdo = app('db')->pdo();
            $database = (string) app('db')->scalar('SELECT DATABASE()');

            $header = "-- HalaOps database backup\n"
                . '-- Database: ' . $database . "\n"
                . '-- Generated: ' . now() . "\n"
                . "-- Generated in pure PHP (no mysqldump).\n\n"
                . "SET FOREIGN_KEY_CHECKS=0;\n"
                . "SET NAMES utf8mb4;\n\n";
            $this->put($handle, $header);

            foreach ($this->baseTables($database) as $table) {
                $this->dumpTable($handle, $pdo, $table);
            }

            $this->put($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        } catch (Throwable $e) {
            fclose($handle);
            @unlink($path);
            throw $e;
        }

        fclose($handle);

        return $path;
    }

    /**
     * Zip the uploads directory (storage/app/uploads, falling back to
     * storage/app) to storage/backups/files-<timestamp>.zip and return the
     * absolute path. Throws if there is genuinely nothing worth backing up so
     * the caller can surface a friendly message instead of an empty archive.
     */
    public function createFilesBackup(): string
    {
        $source = $this->filesSource();
        if ($source === null) {
            throw new RuntimeException('There are no uploaded files to back up yet.');
        }

        $path = $this->dir() . '/files-' . $this->timestamp() . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the files archive.');
        }

        $added = 0;
        $base = rtrim($source, '/');
        $prefix = basename($base);

        /** @var \SplFileInfo[] $iterator */
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $real = $item->getPathname();
            $relative = $prefix . '/' . ltrim(substr($real, strlen($base)), '/');

            if ($item->isDir()) {
                $zip->addEmptyDir($relative);
                continue;
            }

            // Skip unreadable files rather than aborting the whole backup.
            if ($item->isFile() && is_readable($real)) {
                if ($zip->addFile($real, $relative)) {
                    $added++;
                }
            }
        }

        if ($added === 0) {
            $zip->close();
            @unlink($path);
            throw new RuntimeException('There are no uploaded files to back up yet.');
        }

        if (! $zip->close()) {
            @unlink($path);
            throw new RuntimeException('Could not finalise the files archive.');
        }

        return $path;
    }

    /**
     * List the backups currently on disk, newest first.
     *
     * @return array<int, array{name:string,type:string,size:int,size_human:string,created:int,created_human:string}>
     */
    public function list(): array
    {
        $dir = $this->dir();
        $items = [];

        foreach ((array) @scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..' || ! is_string($entry)) {
                continue;
            }

            $full = $dir . '/' . $entry;
            if (! is_file($full)) {
                continue;
            }

            $extension = strtolower((string) pathinfo($entry, PATHINFO_EXTENSION));
            if (! in_array($extension, ['sql', 'zip'], true)) {
                continue;
            }

            $size = (int) (@filesize($full) ?: 0);
            $created = (int) (@filemtime($full) ?: 0);

            $items[] = [
                'name'          => $entry,
                'type'          => $extension === 'sql' ? 'db' : 'files',
                'size'          => $size,
                'size_human'    => $this->humanSize($size),
                'created'       => $created,
                'created_human' => $created > 0 ? date('Y-m-d H:i:s', $created) : '—',
            ];
        }

        usort($items, static fn (array $a, array $b): int => $b['created'] <=> $a['created']);

        return $items;
    }

    /**
     * Resolve a safe absolute path for a backup file, or null when the name is
     * unsafe or the file does not exist. Only the basename is honoured and the
     * resolved path must sit directly inside the backups directory.
     */
    public function path(string $name): ?string
    {
        $safe = $this->safeName($name);
        if ($safe === null) {
            return null;
        }

        $dir = $this->dir();
        $full = $dir . '/' . $safe;

        if (! is_file($full)) {
            return null;
        }

        // Defence in depth: make sure the resolved real path is still inside
        // the backups directory after symlinks etc. are taken into account.
        $realDir = realpath($dir);
        $realFile = realpath($full);
        if ($realDir === false || $realFile === false || ! str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realFile;
    }

    /**
     * Delete a backup file. Silently does nothing for an unknown/unsafe name.
     */
    public function delete(string $name): void
    {
        $path = $this->path($name);
        if ($path === null) {
            throw new RuntimeException('That backup could not be found.');
        }

        if (! @unlink($path)) {
            throw new RuntimeException('Could not delete the backup file.');
        }
    }

    /**
     * Restore the database from a previously created .sql backup, executing it
     * statement-by-statement. Only .sql files that resolve safely inside the
     * backups directory are accepted.
     */
    public function restoreDatabase(string $name): void
    {
        $path = $this->path($name);
        if ($path === null || strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) !== 'sql') {
            throw new RuntimeException('Only a database (.sql) backup can be restored.');
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Could not open the backup file for reading.');
        }

        $db = app('db');
        $buffer = '';

        try {
            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);

                // Skip blank lines and comment lines outside of a statement.
                if ($buffer === '' && ($trimmed === '' || $this->isCommentLine($trimmed))) {
                    continue;
                }

                $buffer .= $line;

                // A statement ends when a line ends with a semicolon.
                if (str_ends_with(rtrim($line, "\r\n"), ';')) {
                    $statement = trim($buffer);
                    $buffer = '';

                    if ($statement === '' || $statement === ';') {
                        continue;
                    }

                    $db->unprepared($statement);
                }
            }

            // Execute any trailing statement that lacked a final newline.
            $tail = trim($buffer);
            if ($tail !== '' && $tail !== ';') {
                $db->unprepared($tail);
            }
        } catch (Throwable $e) {
            fclose($handle);
            throw $e;
        }

        fclose($handle);
    }

    // --- Internals ---------------------------------------------------------

    /**
     * Write one table's DROP + CREATE + data section to the open handle.
     */
    private function dumpTable($handle, PDO $pdo, string $table): void
    {
        $quoted = $this->quoteIdentifier($table);

        $this->put($handle, "\n-- ----------------------------\n");
        $this->put($handle, '-- Table structure for ' . $table . "\n");
        $this->put($handle, "-- ----------------------------\n");
        $this->put($handle, 'DROP TABLE IF EXISTS ' . $quoted . ";\n");

        $create = app('db')->selectOne('SHOW CREATE TABLE ' . $quoted);
        $createSql = is_array($create) ? ($create['Create Table'] ?? $create['Create View'] ?? null) : null;
        if (! is_string($createSql) || $createSql === '') {
            throw new RuntimeException('Could not read the structure for table ' . $table . '.');
        }
        $this->put($handle, $createSql . ";\n\n");

        $this->put($handle, '-- Data for ' . $table . "\n");
        $this->dumpRows($handle, $pdo, $table, $quoted);
    }

    /**
     * Stream the rows of a table as batched INSERT statements.
     */
    private function dumpRows($handle, PDO $pdo, string $table, string $quotedTable): void
    {
        // Unbuffered fetch so a large table does not have to live in memory.
        $statement = $pdo->query('SELECT * FROM ' . $quotedTable);
        if ($statement === false) {
            return;
        }

        $columnsSql = null;
        $batch = [];
        $count = 0;

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if ($columnsSql === null) {
                $columnsSql = implode(', ', array_map(
                    fn (string $col): string => $this->quoteIdentifier($col),
                    array_keys($row)
                ));
            }

            $batch[] = '(' . implode(', ', array_map(
                fn ($value): string => $this->quoteValue($pdo, $value),
                array_values($row)
            )) . ')';
            $count++;

            if ($count >= self::INSERT_BATCH) {
                $this->flushInsert($handle, $quotedTable, $columnsSql, $batch);
                $batch = [];
                $count = 0;
            }
        }

        if ($batch !== [] && $columnsSql !== null) {
            $this->flushInsert($handle, $quotedTable, $columnsSql, $batch);
        }
    }

    /**
     * @param string[] $rows
     */
    private function flushInsert($handle, string $quotedTable, string $columnsSql, array $rows): void
    {
        $sql = 'INSERT INTO ' . $quotedTable . ' (' . $columnsSql . ") VALUES\n"
            . implode(",\n", $rows) . ";\n";
        $this->put($handle, $sql);
    }

    /**
     * Quote a single value for SQL output: NULL literal, raw numeric, or a
     * PDO-quoted string otherwise.
     */
    private function quoteValue(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        // Numeric strings (e.g. DECIMAL/BIGINT returned as strings) are safe to
        // emit raw, but only when they are a clean integer/decimal — anything
        // else (leading zeros, exponents, hex-looking) goes through quote().
        if (is_string($value) && $value !== '' && preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $value) === 1) {
            return $value;
        }

        $quoted = $pdo->quote((string) $value);

        // Extremely defensive: if a driver ever returns false, fall back to a
        // hex literal so the dump stays valid SQL.
        return $quoted === false ? '0x' . bin2hex((string) $value) : $quoted;
    }

    /**
     * Base tables (not views) of the given database, in stable name order.
     *
     * @return string[]
     */
    private function baseTables(string $database): array
    {
        $rows = app('db')->select(
            'SELECT TABLE_NAME AS name
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = ?
                AND TABLE_TYPE = ?
           ORDER BY TABLE_NAME',
            [$database, 'BASE TABLE']
        );

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $rows
        ), static fn (string $name): bool => $name !== ''));
    }

    /**
     * The directory uploads live in, or null when there is nothing to archive.
     */
    private function filesSource(): ?string
    {
        $uploads = storage_path('app/uploads');
        if (is_dir($uploads) && $this->hasEntries($uploads)) {
            return $uploads;
        }

        $appDir = storage_path('app');
        if (is_dir($appDir) && $this->hasEntries($appDir, ['.gitkeep'])) {
            return $appDir;
        }

        return null;
    }

    /**
     * Whether a directory contains anything beyond "." / ".." and an optional
     * ignore list (e.g. a lone .gitkeep is treated as empty).
     *
     * @param string[] $ignore
     */
    private function hasEntries(string $dir, array $ignore = []): bool
    {
        foreach ((array) @scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $ignore, true)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Validate a user-supplied backup name: basename only, no traversal, and a
     * recognised extension. Returns the safe basename or null.
     */
    private function safeName(string $name): ?string
    {
        $base = basename(trim($name));
        if ($base === '' || $base === '.' || $base === '..') {
            return null;
        }

        // Reject anything that tried to be a path or hide separators.
        if ($base !== $name && str_contains($name, '/')) {
            // basename() already stripped directories; only accept names that
            // were a bare filename to begin with.
            return null;
        }
        if (str_contains($base, '/') || str_contains($base, '\\') || str_contains($base, "\0")) {
            return null;
        }

        $extension = strtolower((string) pathinfo($base, PATHINFO_EXTENSION));
        if (! in_array($extension, ['sql', 'zip'], true)) {
            return null;
        }

        return $base;
    }

    private function isCommentLine(string $line): bool
    {
        return str_starts_with($line, '--')
            || str_starts_with($line, '#')
            || str_starts_with($line, '/*');
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function put($handle, string $contents): void
    {
        if (@fwrite($handle, $contents) === false) {
            throw new RuntimeException('Failed while writing the backup file.');
        }
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) min(floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return ($power === 0 ? (string) $bytes : number_format($value, 1)) . ' ' . $units[$power];
    }

    private function timestamp(): string
    {
        return date('Ymd-His');
    }

    /**
     * Absolute path to the backups directory, created on first use.
     */
    private function dir(): string
    {
        $dir = storage_path('backups');
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new RuntimeException('Could not create the backups directory.');
            }
        }

        return $dir;
    }
}
