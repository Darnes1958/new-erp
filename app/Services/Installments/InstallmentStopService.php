<?php

namespace App\Services\Installments;

use App\Models\InstallmentContract;
use App\Models\InstallmentStop;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class InstallmentStopService
{
    public function eligibleContractsQuery(): Builder
    {
        return InstallmentContract::query()
            ->with('customer')
            ->where('balance', '<=', 0)
            ->whereNotIn('id', InstallmentStop::query()->select('installment_contract_id'));
    }

    public function eligibleCount(): int
    {
        return $this->eligibleContractsQuery()->count();
    }

    /**
     * @param  Collection<int, InstallmentContract>  $contracts
     */
    public function stopMany(Collection $contracts, Carbon|string $stopDate): int
    {
        $count = 0;

        foreach ($contracts as $contract) {
            if ((float) $contract->balance > 0) {
                continue;
            }

            if (InstallmentStop::query()->where('installment_contract_id', $contract->id)->exists()) {
                continue;
            }

            InstallmentStop::create([
                'installment_contract_id' => $contract->id,
                'stop_date' => Carbon::parse($stopDate)->toDateString(),
                'created_by' => Auth::id(),
            ]);

            $count++;
        }

        return $count;
    }

    public function isStopped(int $contractId): bool
    {
        return InstallmentStop::query()
            ->where('installment_contract_id', $contractId)
            ->exists();
    }
}
