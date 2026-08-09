<?php

namespace App\Support\Conversion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacySchemaDetector
{
    public static function detect(string $connection): LegacySchemaKind
    {
        if (! config("database.connections.{$connection}")) {
            return LegacySchemaKind::Unknown;
        }

        if (Schema::connection($connection)->hasTable('main')) {
            return LegacySchemaKind::Ins;
        }

        if (Schema::connection($connection)->hasTable('mains')) {
            return LegacySchemaKind::Erp;
        }

        return LegacySchemaKind::Unknown;
    }

    /**
     * @return array<string, int|null>
     */
    public static function tableCounts(string $connection, LegacySchemaKind $kind): array
    {
        $tables = match ($kind) {
            LegacySchemaKind::Ins => [
                'jeha' => 'jeha',
                'place' => 'place',
                'bank' => 'bank',
                'price_type' => 'price_type',
                'items' => 'items',
                'sells' => 'sells',
                'sell_tran' => 'sell_tran',
                'main' => 'main',
                'kst_trans' => 'kst_trans',
                'MainArc' => 'MainArc',
                'buys' => 'buys',
            ],
            LegacySchemaKind::Erp => [
                'customers' => 'customers',
                'places' => 'places',
                'sells' => 'sells',
                'mains' => 'mains',
                'trans' => 'trans',
            ],
            LegacySchemaKind::Unknown => [],
        };

        $counts = [];

        foreach ($tables as $label => $table) {
            $counts[$label] = Schema::connection($connection)->hasTable($table)
                ? DB::connection($connection)->table($table)->count()
                : null;
        }

        return $counts;
    }
}
