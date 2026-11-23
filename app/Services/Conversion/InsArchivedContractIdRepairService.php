<?php

namespace App\Services\Conversion;

use App\Support\Conversion\LegacyConnectionNaming;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class InsArchivedContractIdRepairService
{
    private const TEMP_OFFSET = 8000000;

    /**
     * @return array{remap: int, unchanged: int, offset_for_live: int}
     */
    public function repair(string $legacy, bool $dryRun = false): array
    {
        $legacy = LegacyConnectionNaming::legacyName($legacy);
        $target = LegacyConnectionNaming::targetName($legacy);

        if (! Schema::connection($legacy)->hasTable('MainArc')) {
            throw new RuntimeException("[{$legacy}] لا يحتوي جدول MainArc.");
        }

        if (! Schema::connection($legacy)->hasColumn('MainArc', 'id') || ! Schema::connection($legacy)->hasColumn('MainArc', 'no')) {
            throw new RuntimeException("[{$legacy}] MainArc لا يحتوي العمودين id و no.");
        }

        if (! Schema::connection($target)->hasTable('installment_contract_archives')) {
            throw new RuntimeException("[{$target}] لا يحتوي جدول installment_contract_archives.");
        }

        $liveIds = DB::connection($target)
            ->table('installment_contracts')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $archiveIds = DB::connection($target)
            ->table('installment_contract_archives')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $mappings = [];
        $unchanged = 0;
        $offsetForLive = 0;

        foreach (DB::connection($legacy)->table('MainArc')->select(['id', 'no', 'order_no'])->orderBy('id')->get() as $row) {
            $oldId = (int) $row->id;
            $contractNo = (int) $row->no;

            if (! $archiveIds->has($oldId)) {
                continue;
            }

            $newId = $contractNo > 0 ? $contractNo : $oldId;

            if ($liveIds->has($newId)) {
                $newId = InsMainArc::ID_OFFSET + $contractNo;
                $offsetForLive++;
            }

            if ($newId === $oldId) {
                $unchanged++;

                continue;
            }

            $mappings[] = ['old' => $oldId, 'new' => $newId];
        }

        if ($dryRun || $mappings === []) {
            return [
                'remap' => count($mappings),
                'unchanged' => $unchanged,
                'offset_for_live' => $offsetForLive,
            ];
        }

        DB::connection($target)->transaction(function () use ($target, $mappings): void {
            $this->disableArchiveForeignKeys($target);

            try {
                $this->applyPhase($target, $mappings, self::TEMP_OFFSET, true);
                $this->applyPhase($target, $mappings, self::TEMP_OFFSET, false);
            } finally {
                $this->enableArchiveForeignKeys($target);
            }
        });

        return [
            'remap' => count($mappings),
            'unchanged' => $unchanged,
            'offset_for_live' => $offsetForLive,
        ];
    }

    /**
     * @param  list<array{old: int, new: int}>  $mappings
     */
    protected function applyPhase(string $target, array $mappings, int $tempOffset, bool $toTemp): void
    {
        foreach (array_chunk($mappings, 200) as $chunk) {
            foreach ($chunk as $map) {
                $from = $toTemp ? $map['old'] : $map['old'] + $tempOffset;
                $to = $toTemp ? $map['old'] + $tempOffset : $map['new'];

                $this->remapChildren($target, $from, $to);
                $this->remapArchivePrimaryKey($target, $from, $to);
            }
        }
    }

    protected function remapArchivePrimaryKey(string $target, int $from, int $to): void
    {
        DB::connection($target)
            ->table('installment_contract_archives')
            ->where('id', $from)
            ->update(['id' => $to]);
    }

    protected function remapChildren(string $target, int $from, int $to): void
    {
        $liveExists = DB::connection($target)
            ->table('installment_contracts')
            ->where('id', $from)
            ->exists();

        $this->updateIfTableHasColumn($target, 'installment_deduction_archives', 'installment_contract_id', $from, $to);
        $this->updateIfTableHasColumn($target, 'installment_surplus_archives', 'installment_contract_id', $from, $to);

        if (! $liveExists) {
            $this->updateIfTableHasColumn($target, 'installment_stops', 'installment_contract_id', $from, $to);
            $this->updateIfTableHasColumn($target, 'installment_suspended', 'installment_contract_id', $from, $to);
            $this->updateIfTableHasColumn($target, 'installment_cheques', 'installment_contract_id', $from, $to);
        }

        foreach (['installment_surplus', 'installment_suspended', 'deduction_batch_lines'] as $table) {
            $this->updateMorphId($target, $table, $from, $to);
        }
    }

    protected function updateIfTableHasColumn(string $target, string $table, string $column, int $from, int $to): void
    {
        if (! Schema::connection($target)->hasTable($table) || ! Schema::connection($target)->hasColumn($table, $column)) {
            return;
        }

        DB::connection($target)
            ->table($table)
            ->where($column, $from)
            ->update([$column => $to]);
    }

    protected function updateMorphId(string $target, string $table, int $from, int $to): void
    {
        if (! Schema::connection($target)->hasTable($table) || ! Schema::connection($target)->hasColumn($table, 'contractable_id')) {
            return;
        }

        DB::connection($target)
            ->table($table)
            ->whereIn('contractable_type', ['installment_contract_archive', 'App\\Models\\Main_arc'])
            ->where('contractable_id', $from)
            ->update(['contractable_id' => $to]);
    }

    protected function disableArchiveForeignKeys(string $target): void
    {
        foreach ($this->archiveForeignKeyNames($target) as $name) {
            DB::connection($target)->unprepared("ALTER TABLE [{$this->escapeIdent($name['table'])}] NOCHECK CONSTRAINT [{$this->escapeIdent($name['fk'])}]");
        }
    }

    protected function enableArchiveForeignKeys(string $target): void
    {
        foreach ($this->archiveForeignKeyNames($target) as $name) {
            DB::connection($target)->unprepared("ALTER TABLE [{$this->escapeIdent($name['table'])}] WITH CHECK CHECK CONSTRAINT [{$this->escapeIdent($name['fk'])}]");
        }
    }

    /**
     * @return list<array{fk: string, table: string}>
     */
    protected function archiveForeignKeyNames(string $target): array
    {
        $rows = DB::connection($target)->select(
            "SELECT fk.name AS fk_name, OBJECT_NAME(fk.parent_object_id) AS parent_table
             FROM sys.foreign_keys fk
             INNER JOIN sys.tables t ON fk.referenced_object_id = t.object_id
             WHERE t.name = N'installment_contract_archives'"
        );

        return collect($rows)
            ->map(fn ($row): array => [
                'fk' => (string) $row->fk_name,
                'table' => (string) $row->parent_table,
            ])
            ->all();
    }

    protected function escapeIdent(string $name): string
    {
        return str_replace(']', ']]', $name);
    }
}
