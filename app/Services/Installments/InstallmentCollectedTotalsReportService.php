<?php

namespace App\Services\Installments;

use App\Models\InstallmentCancelledContract;
use App\Models\InstallmentCancelledDeduction;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentDeduction;
use App\Models\InstallmentDeductionArchive;
use App\Models\PayrollBank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InstallmentCollectedTotalsReportService
{
    public function reportTitle(?string $dateFrom, ?string $dateTo): string
    {
        return "تقرير إجمالي المحصلة من تاريخ : {$dateFrom} إلي : {$dateTo}";
    }

    public function reportQuery(?string $dateFrom, ?string $dateTo): Builder
    {
        return PayrollBank::query()
            ->select('payroll_banks.*')
            ->selectSub($this->collectedSubquery($dateFrom, $dateTo, 'active', 'sum'), 'collected_active')
            ->selectSub($this->collectedSubquery($dateFrom, $dateTo, 'archived', 'sum'), 'collected_archived')
            ->selectSub($this->collectedSubquery($dateFrom, $dateTo, 'cancelled', 'sum'), 'collected_cancelled')
            ->selectSub($this->collectedSubquery($dateFrom, $dateTo, 'active', 'count'), 'count_active')
            ->selectSub($this->collectedSubquery($dateFrom, $dateTo, 'archived', 'count'), 'count_archived')
            ->selectSub($this->collectedSubquery($dateFrom, $dateTo, 'cancelled', 'count'), 'count_cancelled')
            ->selectSub($this->expectedSubquery($dateFrom, $dateTo, 'active'), 'expected_active')
            ->selectSub($this->expectedSubquery($dateFrom, $dateTo, 'archived'), 'expected_archived')
            ->selectSub($this->expectedSubquery($dateFrom, $dateTo, 'cancelled'), 'expected_cancelled');
    }

    /**
     * @return Collection<int, PayrollBank>
     */
    public function exportRows(?string $dateFrom, ?string $dateTo): Collection
    {
        return $this->reportQuery($dateFrom, $dateTo)
            ->orderBy('name')
            ->get();
    }

    public function collectedCountFor(PayrollBank $row): int
    {
        return (int) ($row->count_active ?? 0)
            + (int) ($row->count_archived ?? 0)
            + (int) ($row->count_cancelled ?? 0);
    }

    public function collectedTotalFor(PayrollBank $row): float
    {
        return round(
            (float) ($row->collected_active ?? 0)
            + (float) ($row->collected_archived ?? 0)
            + (float) ($row->collected_cancelled ?? 0),
            3,
        );
    }

    public function expectedTotalFor(PayrollBank $row): float
    {
        return round(
            (float) ($row->expected_active ?? 0)
            + (float) ($row->expected_archived ?? 0)
            + (float) ($row->expected_cancelled ?? 0),
            3,
        );
    }

    public function collectionRatioFor(PayrollBank $row): float
    {
        return $this->ratio($this->collectedTotalFor($row), $this->expectedTotalFor($row));
    }

    /**
     * @return array{
     *     collected_count: int,
     *     collected_total: float,
     *     expected_total: float,
     *     collection_ratio: float
     * }
     */
    public function summary(?string $dateFrom, ?string $dateTo): array
    {
        return $this->summaryFromRows($this->exportRows($dateFrom, $dateTo));
    }

    /**
     * @param  Collection<int, PayrollBank>  $rows
     * @return array{
     *     collected_count: int,
     *     collected_total: float,
     *     expected_total: float,
     *     collection_ratio: float
     * }
     */
    public function summaryFromRows(Collection $rows): array
    {
        $collectedTotal = round(
            (float) $rows->sum(fn (PayrollBank $row): float => $this->collectedTotalFor($row)),
            3,
        );
        $expectedTotal = round(
            (float) $rows->sum(fn (PayrollBank $row): float => $this->expectedTotalFor($row)),
            3,
        );

        return [
            'collected_count' => (int) $rows->sum(fn (PayrollBank $row): int => $this->collectedCountFor($row)),
            'collected_total' => $collectedTotal,
            'expected_total' => $expectedTotal,
            'collection_ratio' => $this->ratio($collectedTotal, $expectedTotal),
        ];
    }

    protected function ratio(float $collected, float $expected): float
    {
        if ($expected <= 0) {
            return 0.0;
        }

        return round(($collected / $expected) * 100, 1);
    }

    /**
     * @param  'active'|'archived'|'cancelled'  $source
     * @param  'sum'|'count'  $aggregate
     */
    protected function collectedSubquery(?string $dateFrom, ?string $dateTo, string $source, string $aggregate): Builder
    {
        [$query, $dateColumn] = match ($source) {
            'archived' => [
                InstallmentDeductionArchive::query()
                    ->join(
                        'installment_contract_archives',
                        'installment_contract_archives.id',
                        '=',
                        'installment_deduction_archives.installment_contract_id',
                    )
                    ->whereColumn('installment_contract_archives.payroll_bank_id', 'payroll_banks.id'),
                'installment_deduction_archives.deduction_date',
            ],
            'cancelled' => [
                InstallmentCancelledDeduction::query()
                    ->join(
                        'installment_contracts_cancelled',
                        'installment_contracts_cancelled.id',
                        '=',
                        'installment_deductions_cancelled.installment_contract_id',
                    )
                    ->whereColumn('installment_contracts_cancelled.payroll_bank_id', 'payroll_banks.id'),
                'installment_deductions_cancelled.deduction_date',
            ],
            default => [
                InstallmentDeduction::query()
                    ->join(
                        'installment_contracts',
                        'installment_contracts.id',
                        '=',
                        'installment_deductions.installment_contract_id',
                    )
                    ->whereColumn('installment_contracts.payroll_bank_id', 'payroll_banks.id'),
                'installment_deductions.deduction_date',
            ],
        };

        $amountColumn = match ($source) {
            'archived' => 'installment_deduction_archives.deducted_amount',
            'cancelled' => 'installment_deductions_cancelled.deducted_amount',
            default => 'installment_deductions.deducted_amount',
        };

        $query->selectRaw(
            $aggregate === 'count'
                ? 'coalesce(count(*), 0)'
                : "coalesce(sum({$amountColumn}), 0)",
        );

        if (filled($dateFrom)) {
            $query->whereDate($dateColumn, '>=', $dateFrom);
        }

        if (filled($dateTo)) {
            $query->whereDate($dateColumn, '<=', $dateTo);
        }

        return $query;
    }

    /**
     * @param  'active'|'archived'|'cancelled'  $source
     */
    protected function expectedSubquery(?string $dateFrom, ?string $dateTo, string $source): Builder
    {
        $query = match ($source) {
            'archived' => InstallmentContractArchive::query(),
            'cancelled' => InstallmentCancelledContract::query(),
            default => InstallmentContract::query(),
        };

        $table = $query->getModel()->getTable();
        $endSql = $source === 'cancelled'
            ? "coalesce({$table}.contract_end, {$table}.cancelled_at)"
            : "{$table}.contract_end";

        $from = filled($dateFrom) ? $dateFrom : '1900-01-01';
        $to = filled($dateTo) ? $dateTo : now()->toDateString();

        $overlapStart = "iif({$table}.contract_start > ?, {$table}.contract_start, ?)";
        $overlapEnd = "iif({$endSql} is not null and {$endSql} < ?, {$endSql}, ?)";
        $months = "datediff(month, {$overlapStart}, {$overlapEnd}) + 1";

        $dueMonths = "iif(
            {$months} < 0,
            0,
            iif(
                {$table}.installment_count > 0 and {$months} > {$table}.installment_count,
                {$table}.installment_count,
                {$months}
            )
        )";

        $query
            ->selectRaw(
                "coalesce(sum({$table}.installment_amount * {$dueMonths}), 0)",
                [$from, $from, $to, $to, $from, $from, $to, $to, $from, $from, $to, $to],
            )
            ->whereColumn("{$table}.payroll_bank_id", 'payroll_banks.id')
            ->whereNotNull("{$table}.contract_start")
            ->whereDate("{$table}.contract_start", '<=', $to)
            ->where(function (Builder $builder) use ($endSql, $from): void {
                $builder
                    ->whereRaw("{$endSql} is null")
                    ->orWhereRaw("{$endSql} >= ?", [$from]);
            });

        return $query;
    }
}
