<?php

namespace App\Services\Conversion;

use Illuminate\Support\Facades\Schema;

/**
 * Legacy MainArc differs by company: BenTaher/Motahedon have a surrogate `id`,
 * Elhrer (and possibly others) use contract `no` + unique `order_no` only.
 */
final class InsMainArc
{
    /** Avoid collision with active installment_contracts.id (legacy main.no). */
    public const ID_OFFSET = 9000000;

    public static function hasLegacyIdColumn(string $legacyConnection): bool
    {
        return Schema::connection($legacyConnection)->hasColumn('MainArc', 'id');
    }

    public static function archiveIdFromRow(object $row, string $legacyConnection): int
    {
        if (self::hasLegacyIdColumn($legacyConnection)) {
            return (int) $row->id;
        }

        return self::ID_OFFSET + (int) $row->order_no;
    }

    public static function sqlArchiveIdExpression(string $legacyConnection, string $alias = 'ma'): string
    {
        if (self::hasLegacyIdColumn($legacyConnection)) {
            return "CAST({$alias}.id AS BIGINT)";
        }

        return "CAST({$alias}.order_no AS BIGINT) + ".self::ID_OFFSET;
    }

    public static function sqlMinArchiveIdExpression(string $legacyConnection, string $alias = 'ma'): string
    {
        if (self::hasLegacyIdColumn($legacyConnection)) {
            return "MIN(CAST({$alias}.id AS BIGINT))";
        }

        return 'MIN(CAST('.$alias.'.order_no AS BIGINT) + '.self::ID_OFFSET.')';
    }
}
