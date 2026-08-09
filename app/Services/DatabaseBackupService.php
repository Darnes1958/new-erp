<?php

namespace App\Services;

use App\Support\CompanyConnections;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class DatabaseBackupService
{
    public function createBackupZip(string $connection): string
    {
        if (! CompanyConnections::isValid($connection)) {
            throw new RuntimeException('اتصال الشركة غير صالح.');
        }

        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'sqlsrv') {
            throw new RuntimeException('النسخ الاحتياطي متاح لقواعد SQL Server فقط.');
        }

        if (! extension_loaded('sqlsrv')) {
            throw new RuntimeException('إضافة sqlsrv غير مفعّلة على PHP.');
        }

        $database = (string) ($config['database'] ?? $connection);

        if ($database === '') {
            throw new RuntimeException('اسم قاعدة البيانات غير معرّف.');
        }

        set_time_limit(600);

        if (function_exists('sqlsrv_configure')) {
            sqlsrv_configure('WarningsReturnAsErrors', 0);
        }

        $backupDirectory = $this->backupDiskDirectory();

        $dateStamp = now()->format('Ymd');
        $bakFilename = "{$connection}_{$dateStamp}.bak";
        $zipFilename = "{$connection}_{$dateStamp}.zip";
        $bakPath = $this->normalizeDiskPath($backupDirectory.DIRECTORY_SEPARATOR.$bakFilename);
        $zipPath = $backupDirectory.DIRECTORY_SEPARATOR.$zipFilename;

        foreach ([$bakPath, $zipPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->runSqlServerBackup($database, $bakPath, $config);

        if (! is_file($bakPath)) {
            throw new RuntimeException(
                'فشل إنشاء ملف النسخة الاحتياطية على المسار: '.$bakPath
                .'. تأكد أن حساب خدمة SQL Server لديه صلاحية الكتابة على هذا المجلد،'
                .' أو عيّن ERP_BACKUP_DISK_PATH في .env (مثل C:\backup).'
            );
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            unlink($bakPath);

            throw new RuntimeException('تعذّر إنشاء ملف ZIP.');
        }

        $zip->addFile($bakPath, $bakFilename);
        $zip->close();
        unlink($bakPath);

        if (! is_file($zipPath)) {
            throw new RuntimeException('فشل ضغط ملف النسخة الاحتياطية.');
        }

        return $zipPath;
    }

    protected function backupDiskDirectory(): string
    {
        $configured = config('erp.backup_disk_path');

        $directory = is_string($configured) && $configured !== ''
            ? $configured
            : storage_path('app');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('تعذّر إنشاء مجلد النسخ الاحتياطي: '.$directory);
        }

        if (! is_writable($directory)) {
            throw new RuntimeException('مجلد النسخ الاحتياطي غير قابل للكتابة من PHP: '.$directory);
        }

        return $directory;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function runSqlServerBackup(string $database, string $bakPath, array $config): void
    {
        if ($this->shouldUseNativeSqlsrv($config)) {
            $this->runNativeSqlServerBackup($database, $bakPath, $config);

            return;
        }

        $escapedPath = str_replace("'", "''", $bakPath);
        $escapedDatabase = str_replace(']', ']]', $database);

        $masterConnection = '__database_backup_master';

        config([
            "database.connections.{$masterConnection}" => array_merge($config, [
                'database' => 'master',
            ]),
        ]);

        DB::purge($masterConnection);

        $sql = "BACKUP DATABASE [{$escapedDatabase}] TO DISK = N'{$escapedPath}' WITH FORMAT, INIT, NAME = N'{$escapedDatabase}-Backup', SKIP, NOREWIND, NOUNLOAD, STATS = 10";

        try {
            DB::connection($masterConnection)->unprepared($sql);
        } catch (QueryException $exception) {
            throw new RuntimeException('فشل أمر BACKUP DATABASE: '.$exception->getMessage(), previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function shouldUseNativeSqlsrv(array $config): bool
    {
        return extension_loaded('sqlsrv') && filled($config['username'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function runNativeSqlServerBackup(string $database, string $bakPath, array $config): void
    {
        $serverName = $this->serverName($config);

        $connectionInfo = [
            'Database' => 'master',
            'UID' => (string) $config['username'],
            'PWD' => (string) ($config['password'] ?? ''),
            'CharacterSet' => 'UTF-8',
            'TrustServerCertificate' => (bool) ($config['trust_server_certificate'] ?? true),
        ];

        $connection = sqlsrv_connect($serverName, $connectionInfo);

        if ($connection === false) {
            throw new RuntimeException('تعذّر الاتصال بـ SQL Server: '.$this->formatSqlsrvErrors());
        }

        $escapedPath = str_replace("'", "''", $bakPath);
        $escapedDatabase = str_replace(']', ']]', $database);

        $sql = "BACKUP DATABASE [{$escapedDatabase}] TO DISK = N'{$escapedPath}' WITH FORMAT, INIT, NAME = N'{$escapedDatabase}-Backup', SKIP, NOREWIND, NOUNLOAD, STATS = 10";

        $statement = sqlsrv_query($connection, $sql);

        if ($statement === false) {
            $message = $this->formatSqlsrvErrors($connection);
            sqlsrv_close($connection);

            throw new RuntimeException('فشل أمر BACKUP DATABASE: '.$message);
        }

        do {
            while (sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
                // Drain progress/result rows from BACKUP.
            }
        } while (sqlsrv_next_result($statement));

        sqlsrv_free_stmt($statement);
        sqlsrv_close($connection);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function serverName(array $config): string
    {
        $host = (string) ($config['host'] ?? 'localhost');

        if (in_array(strtolower($host), ['localhost', '127.0.0.1'], true)) {
            $host = '.';
        }

        $port = $config['port'] ?? null;

        if ($port !== null && $port !== '' && ! str_contains($host, ',')) {
            return $host.','.$port;
        }

        return $host;
    }

    protected function normalizeDiskPath(string $path): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    protected function formatSqlsrvErrors($connection = null): string
    {
        $errors = $connection === null
            ? sqlsrv_errors(SQLSRV_ERR_ALL)
            : sqlsrv_errors(SQLSRV_ERR_ALL);

        if (! is_array($errors) || $errors === []) {
            return 'خطأ SQL Server غير معروف.';
        }

        return collect($errors)
            ->map(fn (array $error): string => trim(($error['SQLSTATE'] ?? '').' '.($error['code'] ?? '').': '.($error['message'] ?? '')))
            ->implode(' | ');
    }
}
