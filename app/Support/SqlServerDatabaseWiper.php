<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Drops SQL Server objects without sp_msforeachtable (often unavailable when
 * guest is disabled in master / on hardened hosts).
 */
class SqlServerDatabaseWiper
{
    public function wipe(string $connection): void
    {
        if (! config("database.connections.{$connection}")) {
            throw new RuntimeException("Database connection [{$connection}] is not configured.");
        }

        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'sqlsrv') {
            throw new RuntimeException("SqlServerDatabaseWiper only supports sqlsrv, got [{$driver}].");
        }

        $db = DB::connection($connection);

        $db->unprepared(<<<'SQL'
DECLARE @sql NVARCHAR(MAX) = N'';
SELECT @sql += N'ALTER TABLE '
    + QUOTENAME(OBJECT_SCHEMA_NAME(parent_object_id)) + N'.'
    + QUOTENAME(OBJECT_NAME(parent_object_id))
    + N' DROP CONSTRAINT ' + QUOTENAME(name) + N';'
FROM sys.foreign_keys;
IF @sql <> N'' EXEC sp_executesql @sql;
SQL);

        $db->unprepared(<<<'SQL'
DECLARE @sql NVARCHAR(MAX) = N'';
SELECT @sql += N'DROP VIEW '
    + QUOTENAME(OBJECT_SCHEMA_NAME(object_id)) + N'.'
    + QUOTENAME(name) + N';'
FROM sys.views
WHERE is_ms_shipped = 0;
IF @sql <> N'' EXEC sp_executesql @sql;
SQL);

        $db->unprepared(<<<'SQL'
DECLARE @sql NVARCHAR(MAX) = N'';
SELECT @sql += N'DROP TABLE '
    + QUOTENAME(OBJECT_SCHEMA_NAME(object_id)) + N'.'
    + QUOTENAME(name) + N';'
FROM sys.tables
WHERE is_ms_shipped = 0
  AND name <> N'sysdiagrams';
IF @sql <> N'' EXEC sp_executesql @sql;
SQL);
    }
}
