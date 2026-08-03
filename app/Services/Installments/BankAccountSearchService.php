<?php

namespace App\Services\Installments;

use App\Models\DeductionBatch;
use App\Models\InstallmentCancelledContract;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\WrongDeductionAccount;
use App\Support\InstallmentBankScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BankAccountSearchService
{
    public const MIN_SEARCH_LENGTH = 4;

    public const DEFAULT_LIMIT = 20;

    /**
     * @return array<string, string>
     */
    public function searchForBatch(DeductionBatch $batch, ?string $search, int $limit = self::DEFAULT_LIMIT): array
    {
        return $this->search(
            $batch->getConnectionName(),
            $batch->payroll_bank_id,
            $batch->installment_bank_id,
            $search,
            $limit,
        );
    }

    /**
     * @return array<string, string>
     */
    public function search(
        ?string $connection,
        ?int $payrollBankId,
        ?int $installmentBankId,
        ?string $search,
        int $limit = self::DEFAULT_LIMIT,
    ): array {
        $search = trim((string) $search);

        if (strlen($search) < self::MIN_SEARCH_LENGTH) {
            return [];
        }

        $active = $this->matchingActiveContracts($connection, $payrollBankId, $installmentBankId, $search);
        $archives = $this->matchingArchiveContracts($connection, $payrollBankId, $installmentBankId, $search);
        $cancelled = $this->matchingCancelledContracts($connection, $payrollBankId, $installmentBankId, $search);

        /** @var Collection<string, Collection<int, InstallmentContract|InstallmentContractArchive|InstallmentCancelledContract>> $grouped */
        $grouped = $active
            ->concat($archives)
            ->concat($cancelled)
            ->filter(fn ($contract): bool => filled($contract->bank_account_number))
            ->groupBy('bank_account_number');

        $results = [];

        foreach ($grouped as $account => $contracts) {
            $results[(string) $account] = $this->formatAccountLabel($contracts);

            if (count($results) >= $limit) {
                break;
            }
        }

        foreach ($this->matchingWrongAccounts($connection, $payrollBankId, $installmentBankId, $search, $limit) as $wrong) {
            $account = (string) $wrong->account_number;

            if (isset($results[$account])) {
                continue;
            }

            $results[$account] = $this->formatWrongAccountLabel($wrong);

            if (count($results) >= $limit) {
                break;
            }
        }

        return $this->appendManualAccountOption($results, $search);
    }

    /**
     * @param  array<string, string>  $results
     * @return array<string, string>
     */
    public function appendManualAccountOption(array $results, ?string $search): array
    {
        $search = trim((string) $search);

        if (strlen($search) < self::MIN_SEARCH_LENGTH || isset($results[$search])) {
            return $results;
        }

        $results[$search] = $search.' — [حساب جديد — بالخطأ]';

        return $results;
    }

    public function labelForAccount(
        ?string $connection,
        ?int $payrollBankId,
        ?int $installmentBankId,
        string $account,
    ): ?string {
        $account = trim($account);

        if ($account === '') {
            return null;
        }

        $active = $this->scopedActiveQuery($connection, $payrollBankId, $installmentBankId)
            ->with('customer')
            ->where('bank_account_number', $account)
            ->get();

        $archives = $this->scopedArchiveQuery($connection, $payrollBankId, $installmentBankId)
            ->with('customer')
            ->where('bank_account_number', $account)
            ->get();

        $cancelled = $this->scopedCancelledQuery($connection, $payrollBankId, $installmentBankId)
            ->with('customer')
            ->where('bank_account_number', $account)
            ->get();

        $contracts = $active->concat($archives)->concat($cancelled);

        if ($contracts->isNotEmpty()) {
            return $this->formatAccountLabel($contracts);
        }

        $wrong = $this->findWrongAccount($connection, $payrollBankId, $installmentBankId, $account);

        if ($wrong) {
            return $this->formatWrongAccountLabel($wrong);
        }

        return $account.' — [حساب جديد — بالخطأ]';
    }

    /**
     * @return Collection<int, InstallmentContract>
     */
    protected function matchingActiveContracts(
        ?string $connection,
        ?int $payrollBankId,
        ?int $installmentBankId,
        string $search,
    ): Collection {
        return $this->scopedActiveQuery($connection, $payrollBankId, $installmentBankId)
            ->with('customer')
            ->where(function (Builder $query) use ($search): void {
                $this->applySearchConditions($query, $search);
            })
            ->orderBy('bank_account_number')
            ->orderBy('id')
            ->limit(100)
            ->get();
    }

    /**
     * @return Collection<int, InstallmentContractArchive>
     */
    protected function matchingArchiveContracts(
        ?string $connection,
        ?int $payrollBankId,
        ?int $installmentBankId,
        string $search,
    ): Collection {
        return $this->scopedArchiveQuery($connection, $payrollBankId, $installmentBankId)
            ->with('customer')
            ->where(function (Builder $query) use ($search): void {
                $this->applySearchConditions($query, $search);
            })
            ->orderBy('bank_account_number')
            ->orderBy('id')
            ->limit(100)
            ->get();
    }

    /**
     * @return Collection<int, InstallmentCancelledContract>
     */
    protected function matchingCancelledContracts(
        ?string $connection,
        ?int $payrollBankId,
        ?int $installmentBankId,
        string $search,
    ): Collection {
        return $this->scopedCancelledQuery($connection, $payrollBankId, $installmentBankId)
            ->with('customer')
            ->where(function (Builder $query) use ($search): void {
                $this->applySearchConditions($query, $search);
            })
            ->orderBy('bank_account_number')
            ->orderBy('id')
            ->limit(100)
            ->get();
    }

    protected function scopedActiveQuery(
        ?string $connection,
        ?int $payrollBankId,
        ?int $installmentBankId,
    ): Builder {
        $query = $connection
            ? InstallmentContract::on($connection)->newQuery()
            : InstallmentContract::query();

        $query
            ->whereNotNull('bank_account_number')
            ->where('bank_account_number', '!=', '');

        InstallmentBankScope::applyScope($query, $payrollBankId, $installmentBankId);

        return $query;
    }

    protected function scopedArchiveQuery(
        ?string $connection,
        ?int $payrollBankId,
        ?int $installmentBankId,
    ): Builder {
        $query = $connection
            ? InstallmentContractArchive::on($connection)->newQuery()
            : InstallmentContractArchive::query();

        $query
            ->whereNotNull('bank_account_number')
            ->where('bank_account_number', '!=', '');

        InstallmentBankScope::applyScope($query, $payrollBankId, $installmentBankId);

        return $query;
    }

    protected function scopedCancelledQuery(
        ?string $connection,
        ?int $payrollBankId,
        ?int $installmentBankId,
    ): Builder {
        $query = $connection
            ? InstallmentCancelledContract::on($connection)->newQuery()
            : InstallmentCancelledContract::query();

        $query
            ->whereNotNull('bank_account_number')
            ->where('bank_account_number', '!=', '');

        InstallmentBankScope::applyScope($query, $payrollBankId, $installmentBankId);

        return $query;
    }

    protected function applySearchConditions(Builder $query, string $search): void
    {
        $query->where(function (Builder $inner) use ($search): void {
            $inner->where('bank_account_number', 'like', "{$search}%")
                ->orWhere('bank_account_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', "%{$search}%"));
        });
    }

    /**
     * @param  Collection<int, InstallmentContract|InstallmentContractArchive>  $contracts
     */
    protected function formatAccountLabel(Collection $contracts): string
    {
        $account = (string) $contracts->first()->bank_account_number;
        $customerName = (string) ($contracts->first()->customer?->name ?? '');

        $activeIds = $contracts
            ->filter(fn ($contract): bool => $contract instanceof InstallmentContract)
            ->pluck('id')
            ->unique()
            ->values();

        $archiveIds = $contracts
            ->filter(fn ($contract): bool => $contract instanceof InstallmentContractArchive)
            ->pluck('id')
            ->unique()
            ->values();

        $cancelledIds = $contracts
            ->filter(fn ($contract): bool => $contract instanceof InstallmentCancelledContract)
            ->pluck('id')
            ->unique()
            ->values();

        $contractHint = match (true) {
            $activeIds->count() === 1 && $archiveIds->isEmpty() && $cancelledIds->isEmpty() => 'عقد '.$activeIds->first(),
            $activeIds->count() > 1 => $activeIds->count().' عقود',
            $archiveIds->count() === 1 && $activeIds->isEmpty() && $cancelledIds->isEmpty() => '[أرشيف] عقد '.$archiveIds->first(),
            $archiveIds->count() > 1 => '[أرشيف] '.$archiveIds->count().' عقود',
            $cancelledIds->count() === 1 && $activeIds->isEmpty() && $archiveIds->isEmpty() => '[ملغي] عقد '.$cancelledIds->first(),
            $cancelledIds->count() > 1 => '[ملغي] '.$cancelledIds->count().' عقود',
            default => null,
        };

        $parts = array_filter([$account, $customerName, $contractHint]);

        return implode(' — ', $parts);
    }

    /**
     * @return Collection<int, WrongDeductionAccount>
     */
    protected function matchingWrongAccounts(
        ?string $connection,
        ?int $payrollBankId,
        ?int $installmentBankId,
        string $search,
        int $limit,
    ): Collection {
        $query = $connection
            ? WrongDeductionAccount::on($connection)->newQuery()
            : WrongDeductionAccount::query();

        $this->applyWrongAccountScope($query, $payrollBankId, $installmentBankId);

        return $query
            ->where(function (Builder $inner) use ($search): void {
                $inner->where('account_number', 'like', "{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            })
            ->orderBy('account_number')
            ->limit($limit)
            ->get();
    }

    protected function findWrongAccount(
        ?string $connection,
        ?int $payrollBankId,
        ?int $installmentBankId,
        string $account,
    ): ?WrongDeductionAccount {
        $query = $connection
            ? WrongDeductionAccount::on($connection)->newQuery()
            : WrongDeductionAccount::query();

        $this->applyWrongAccountScope($query, $payrollBankId, $installmentBankId);

        return $query->where('account_number', $account)->first();
    }

    protected function applyWrongAccountScope(
        Builder $query,
        ?int $payrollBankId,
        ?int $installmentBankId,
    ): void {
        if ($payrollBankId) {
            $query->where('payroll_bank_id', $payrollBankId);
        }

        if ($installmentBankId) {
            $query->where(function (Builder $inner) use ($installmentBankId): void {
                $inner->whereNull('installment_bank_id')
                    ->orWhere('installment_bank_id', $installmentBankId);
            });
        }
    }

    protected function formatWrongAccountLabel(WrongDeductionAccount $wrong): string
    {
        return implode(' — ', array_filter([
            $wrong->account_number,
            $wrong->name,
            '[بالخطأ]',
        ]));
    }
}
