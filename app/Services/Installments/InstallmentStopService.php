<?php

namespace App\Services\Installments;

use App\Models\InstallmentContract;
use App\Models\InstallmentStop;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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

    /**
     * @param  Collection<int, InstallmentContract>  $contracts
     * @return array{stopped: int, archived: int, skipped_unpaid: int, errors: list<string>}
     */
    public function stopAndArchiveMany(Collection $contracts, Carbon|string $stopDate): array
    {
        $archiveService = app(InstallmentContractArchiveService::class);
        $stopped = $this->stopMany($contracts, $stopDate);
        $archived = 0;
        $skippedUnpaid = 0;
        $errors = [];

        foreach ($contracts as $contract) {
            $contract->refresh();

            if ((float) $contract->balance > 0) {
                $skippedUnpaid++;

                continue;
            }

            try {
                $archiveService->moveFromActive($contract);
                $archived++;
            } catch (ValidationException $exception) {
                $errors[] = 'عقد '.$contract->id.': '.(collect($exception->errors())->flatten()->first() ?? 'تعذر النقل إلى الأرشيف');
            }
        }

        return [
            'stopped' => $stopped,
            'archived' => $archived,
            'skipped_unpaid' => $skippedUnpaid,
            'errors' => $errors,
        ];
    }
}
