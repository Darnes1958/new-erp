<?php

namespace App\Services\Conversion;

use App\Support\Conversion\LegacyConnectionNaming;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class InsArchivedStopRepairService
{
    /**
     * @return array{moved: int, already_linked: int, skipped: int}
     */
    public function repair(string $legacy, bool $dryRun = false): array
    {
        $legacy = LegacyConnectionNaming::legacyName($legacy);
        $target = LegacyConnectionNaming::targetName($legacy);

        if (! Schema::connection($legacy)->hasTable('stop_kst')) {
            throw new RuntimeException("[{$legacy}] لا يحتوي جدول stop_kst.");
        }

        foreach (['installment_stops', 'installment_stops_without_contract', 'installment_contract_archives'] as $table) {
            if (! Schema::connection($target)->hasTable($table)) {
                throw new RuntimeException("[{$target}] لا يحتوي جدول {$table}.");
            }
        }

        $moved = 0;
        $alreadyLinked = 0;
        $skipped = 0;

        $legacyStops = DB::connection($legacy)
            ->table('stop_kst')
            ->select(['rec_no', 'no', 'stop_date', 'emp'])
            ->orderBy('rec_no')
            ->get();

        $withoutById = DB::connection($target)
            ->table('installment_stops_without_contract')
            ->get()
            ->keyBy(fn ($row) => (int) $row->id);

        $activeIds = DB::connection($target)
            ->table('installment_contracts')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $archiveIds = DB::connection($target)
            ->table('installment_contract_archives')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $existingStopIds = DB::connection($target)
            ->table('installment_stops')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $existingStopContractIds = DB::connection($target)
            ->table('installment_stops')
            ->pluck('installment_contract_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $archiveIdByContractNo = $this->archiveIdByContractNo($legacy);

        $toInsert = [];
        $toDelete = [];

        foreach ($legacyStops as $legacyStop) {
            $stopId = (int) $legacyStop->rec_no;
            $contractNo = (int) $legacyStop->no;

            if (! $withoutById->has($stopId)) {
                continue;
            }

            $contractId = null;

            if ($activeIds->has($contractNo)) {
                $contractId = $contractNo;
            } elseif ($archiveIdByContractNo->has($contractNo) && $archiveIds->has((int) $archiveIdByContractNo->get($contractNo))) {
                $contractId = (int) $archiveIdByContractNo->get($contractNo);
            } elseif ($archiveIds->has($contractNo)) {
                $contractId = $contractNo;
            }

            if ($contractId === null) {
                $skipped++;

                continue;
            }

            $without = $withoutById->get($stopId);

            if ($existingStopIds->has($stopId) || $existingStopContractIds->has($contractId)) {
                $toDelete[] = $stopId;
                $alreadyLinked++;

                continue;
            }

            $toInsert[] = [
                'id' => $stopId,
                'installment_contract_id' => $contractId,
                'stop_date' => $legacyStop->stop_date ?? $without->stop_date,
                'created_by' => $without->created_by,
                'created_at' => $without->created_at,
                'updated_at' => $without->updated_at,
            ];
            $toDelete[] = $stopId;
            $existingStopIds[$stopId] = true;
            $existingStopContractIds[$contractId] = true;
            $moved++;
        }

        if ($dryRun) {
            return [
                'moved' => $moved,
                'already_linked' => $alreadyLinked,
                'skipped' => $skipped,
            ];
        }

        DB::connection($target)->transaction(function () use ($target, $toInsert, $toDelete): void {
            if ($toInsert !== []) {
                DB::connection($target)->unprepared('SET IDENTITY_INSERT installment_stops ON');

                try {
                    foreach (array_chunk($toInsert, 200) as $chunk) {
                        DB::connection($target)->table('installment_stops')->insert($chunk);
                    }
                } finally {
                    DB::connection($target)->unprepared('SET IDENTITY_INSERT installment_stops OFF');
                }
            }

            foreach (array_chunk($toDelete, 200) as $chunk) {
                DB::connection($target)
                    ->table('installment_stops_without_contract')
                    ->whereIn('id', $chunk)
                    ->delete();
            }
        });

        return [
            'moved' => $moved,
            'already_linked' => $alreadyLinked,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return Collection<int, int>
     */
    protected function archiveIdByContractNo(string $legacy): Collection
    {
        if (! Schema::connection($legacy)->hasTable('MainArc')) {
            return collect();
        }

        if (InsMainArc::hasLegacyIdColumn($legacy)) {
            $rows = DB::connection($legacy)
                ->table('MainArc')
                ->selectRaw('no, MIN(CAST(id AS BIGINT)) AS archive_id')
                ->groupBy('no')
                ->get();
        } else {
            $offset = InsMainArc::ID_OFFSET;
            $rows = DB::connection($legacy)
                ->table('MainArc')
                ->selectRaw('no, MIN(CAST(order_no AS BIGINT) + ?) AS archive_id', [$offset])
                ->groupBy('no')
                ->get();
        }

        return $rows->mapWithKeys(fn ($row) => [(int) $row->no => (int) $row->archive_id]);
    }
}
