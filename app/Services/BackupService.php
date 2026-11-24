<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class BackupService
{
    private const BACKUP_DIR = 'backups';
    private const MAX_BACKUPS_TO_KEEP = 7;

    /**
     * Create a complete system backup (Database SQL + Media Files + Manifest).
     *
     * @param bool $includeMedia Whether to include storage/app/public
     * @return array Backup summary metadata
     */
    public function createBackup(bool $includeMedia = true): array
    {
        $startTime = microtime(true);
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $backupDir = storage_path('app/' . self::BACKUP_DIR);

        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0750, true, true);
            // Write protection files
            File::put($backupDir . '/.gitignore', "*\n!.gitignore\n");
            File::put($backupDir . '/.htaccess', "Deny from all\n");
        }

        $zipFileName = "tedallal_backup_{$timestamp}.zip";
        $zipFilePath = $backupDir . DIRECTORY_SEPARATOR . $zipFileName;
        $tempSqlPath = $backupDir . DIRECTORY_SEPARATOR . "db_{$timestamp}.sql";

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("تعذر إنشاء أرشيف النسخة الاحتياطية في المسار: {$zipFilePath}");
        }

        // 1. Export Database
        $dbStats = $this->dumpDatabaseToSql($tempSqlPath);
        $zip->addFile($tempSqlPath, 'database/database.sql');

        // 2. Archive Media Files
        $mediaStats = ['files_count' => 0, 'total_size_bytes' => 0];
        if ($includeMedia) {
            $mediaStats = $this->archiveMediaFolder($zip, storage_path('app/public'));
        }

        // 3. Generate Manifest
        $durationSeconds = round(microtime(true) - $startTime, 2);
        $manifest = [
            'app_name' => 'منصة تدلّل | Tedallal Platform',
            'version' => '2.0-secure',
            'created_at' => Carbon::now()->toIso8601String(),
            'environment' => config('app.env'),
            'duration_seconds' => $durationSeconds,
            'database' => [
                'connection' => config('database.default'),
                'tables_count' => $dbStats['tables_count'],
                'rows_count' => $dbStats['total_rows'],
                'sql_size_bytes' => $dbStats['file_size_bytes'],
            ],
            'media' => [
                'included' => $includeMedia,
                'files_count' => $mediaStats['files_count'],
                'total_size_bytes' => $mediaStats['total_size_bytes'],
            ],
        ];

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        // Remove temporary SQL file
        if (File::exists($tempSqlPath)) {
            File::delete($tempSqlPath);
        }

        $finalZipSize = File::size($zipFilePath);

        // 4. Prune older backups
        $this->pruneOldBackups();

        ActivityLogger::log(
            'system_backup_created',
            "تم إنشاء نسخة احتياطية جديدة للنظام ({$zipFileName}) بحجم " . $this->formatBytes($finalZipSize)
        );

        Log::info("Tedallal Backup Created Successfully", [
            'file' => $zipFileName,
            'size' => $finalZipSize,
            'duration' => $durationSeconds,
        ]);

        return [
            'filename' => $zipFileName,
            'path' => $zipFilePath,
            'size_bytes' => $finalZipSize,
            'size_formatted' => $this->formatBytes($finalZipSize),
            'duration_seconds' => $durationSeconds,
            'created_at' => Carbon::now()->toDateTimeString(),
            'tables_count' => $dbStats['tables_count'],
            'rows_count' => $dbStats['total_rows'],
            'media_files_count' => $mediaStats['files_count'],
        ];
    }

    /**
     * Export all database tables cleanly using PDO streaming to ensure zero memory exhaustion.
     */
    private function dumpDatabaseToSql(string $outputFile): array
    {
        $handle = fopen($outputFile, 'w');
        if (! $handle) {
            throw new \RuntimeException("تعذر فتح ملف التفريغ للكتابة: {$outputFile}");
        }

        $driver = DB::connection()->getDriverName();
        $pdo = DB::connection()->getPdo();

        fwrite($handle, "-- ==========================================================\n");
        fwrite($handle, "-- Tedallal Platform Database Backup\n");
        fwrite($handle, "-- Generated: " . Carbon::now()->toDateTimeString() . "\n");
        fwrite($handle, "-- Driver: " . $driver . "\n");
        fwrite($handle, "-- ==========================================================\n\n");

        if ($driver === 'mysql' || $driver === 'mariadb') {
            fwrite($handle, "SET NAMES utf8mb4;\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
            fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
            fwrite($handle, "SET AUTOCOMMIT = 0;\n");
            fwrite($handle, "START TRANSACTION;\n\n");
        } elseif ($driver === 'sqlite') {
            fwrite($handle, "PRAGMA foreign_keys = OFF;\n");
            fwrite($handle, "BEGIN TRANSACTION;\n\n");
        }

        $allTables = Schema::getTables();
        $tablesCount = 0;
        $totalRows = 0;

        foreach ($allTables as $tableInfo) {
            $tableName = $tableInfo['name'];
            $tablesCount++;

            fwrite($handle, "\n-- ----------------------------------------------------------\n");
            fwrite($handle, "-- Table structure and data for `{$tableName}`\n");
            fwrite($handle, "-- ----------------------------------------------------------\n\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n");

            // Get Create Table SQL
            $createSql = null;
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?");
                $stmt->execute([$tableName]);
                $createSql = $stmt->fetchColumn();
            } else {
                $createStmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`")->fetch(\PDO::FETCH_ASSOC);
                $createSql = $createStmt['Create Table'] ?? null;
            }

            if ($createSql) {
                fwrite($handle, $createSql . ";\n\n");
            }

            // Stream rows in batches
            $dataStmt = $pdo->query("SELECT * FROM `{$tableName}`");
            $rowsBatch = [];
            $batchSize = 250;

            while ($row = $dataStmt->fetch(\PDO::FETCH_ASSOC)) {
                $totalRows++;
                $escapedValues = array_map(function ($val) use ($pdo) {
                    if ($val === null) {
                        return 'NULL';
                    }
                    return $pdo->quote((string) $val);
                }, array_values($row));

                $rowsBatch[] = '(' . implode(', ', $escapedValues) . ')';

                if (count($rowsBatch) >= $batchSize) {
                    $insertSql = "INSERT INTO `{$tableName}` VALUES \n" . implode(",\n", $rowsBatch) . ";\n";
                    fwrite($handle, $insertSql);
                    $rowsBatch = [];
                }
            }

            if (! empty($rowsBatch)) {
                $insertSql = "INSERT INTO `{$tableName}` VALUES \n" . implode(",\n", $rowsBatch) . ";\n";
                fwrite($handle, $insertSql);
            }
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            fwrite($handle, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
            fwrite($handle, "COMMIT;\n");
        } elseif ($driver === 'sqlite') {
            fwrite($handle, "\nPRAGMA foreign_keys = ON;\n");
            fwrite($handle, "COMMIT;\n");
        }

        fclose($handle);

        return [
            'tables_count' => $tablesCount,
            'total_rows' => $totalRows,
            'file_size_bytes' => File::size($outputFile),
        ];
    }

    /**
     * Recursively add public media folder to zip.
     */
    private function archiveMediaFolder(ZipArchive $zip, string $folderPath): array
    {
        if (! File::isDirectory($folderPath)) {
            return ['files_count' => 0, 'total_size_bytes' => 0];
        }

        $filesCount = 0;
        $totalBytes = 0;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (! $file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = 'media/' . substr($filePath, strlen($folderPath) + 1);
                $zip->addFile($filePath, str_replace('\\', '/', $relativePath));
                $filesCount++;
                $totalBytes += $file->getSize();
            }
        }

        return [
            'files_count' => $filesCount,
            'total_size_bytes' => $totalBytes,
        ];
    }

    /**
     * List all existing backup files sorted by newest first.
     */
    public function listBackups(): array
    {
        $backupDir = storage_path('app/' . self::BACKUP_DIR);
        if (! File::isDirectory($backupDir)) {
            return [];
        }

        $files = File::glob($backupDir . DIRECTORY_SEPARATOR . 'tedallal_backup_*.zip');
        $backups = [];

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $size = File::size($filePath);
            $lastModified = File::lastModified($filePath);

            $backups[] = [
                'filename' => $filename,
                'size_bytes' => $size,
                'size_formatted' => $this->formatBytes($size),
                'created_at' => Carbon::createFromTimestamp($lastModified)->toDateTimeString(),
                'created_at_human' => Carbon::createFromTimestamp($lastModified)->diffForHumans(),
            ];
        }

        // Sort newest first
        usort($backups, fn ($a, $b) => strcmp($b['filename'], $a['filename']));

        return $backups;
    }

    /**
     * Get absolute path of a specific backup file safely (anti directory-traversal).
     */
    public function getBackupPath(string $filename): ?string
    {
        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        if (! preg_match('/^tedallal_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/', $filename)) {
            return null;
        }

        $filePath = storage_path('app/' . self::BACKUP_DIR . DIRECTORY_SEPARATOR . $filename);
        if (File::exists($filePath)) {
            return $filePath;
        }

        return null;
    }

    /**
     * Delete a backup file.
     */
    public function deleteBackup(string $filename): bool
    {
        $path = $this->getBackupPath($filename);
        if ($path && File::exists($path)) {
            File::delete($path);
            ActivityLogger::log('system_backup_deleted', "تم حذف النسخة الاحتياطية: {$filename}");
            return true;
        }
        return false;
    }

    /**
     * Automatically prune older backups exceeding the retention threshold.
     */
    private function pruneOldBackups(): void
    {
        $backups = $this->listBackups();
        if (count($backups) <= self::MAX_BACKUPS_TO_KEEP) {
            return;
        }

        $toDelete = array_slice($backups, self::MAX_BACKUPS_TO_KEEP);
        foreach ($toDelete as $item) {
            $path = storage_path('app/' . self::BACKUP_DIR . DIRECTORY_SEPARATOR . $item['filename']);
            if (File::exists($path)) {
                File::delete($path);
                Log::info("Pruned old backup file: {$item['filename']}");
            }
        }
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
