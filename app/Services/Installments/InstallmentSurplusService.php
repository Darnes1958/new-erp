<?php

namespace App\Services\Installments;

use App\Enums\InstallmentRecordStatus;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentSurplus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallmentSurplusService
{
    public function __construct(
        protected InstallmentReturnService $returns,
    ) {}

    public function createManual(
        InstallmentContract|InstallmentContractArchive $contract,
        Carbon|string $surplusDate,
        float $amount,
    ): InstallmentSurplus {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'يجب إدخال قيمة الفائض.',
            ]);
        }

        return $contract->surpluses()->create([
            'surplus_date' => $surplusDate,
            'amount' => $amount,
            'status' => InstallmentRecordStatus::Open,
            'created_by' => Auth::id(),
        ]);
    }

    public function update(
        InstallmentSurplus $surplus,
        Carbon|string $surplusDate,
        float $amount,
    ): InstallmentSurplus {
        if (! $surplus->isEditable()) {
            throw ValidationException::withMessages([
                'surplus' => 'لا يمكن تعديل هذا الفائض.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'يجب إدخال قيمة الفائض.',
            ]);
        }

        $surplus->update([
            'surplus_date' => $surplusDate,
            'amount' => $amount,
        ]);

        return $surplus->refresh();
    }

    public function delete(InstallmentSurplus $surplus): void
    {
        if (! $surplus->isEditable()) {
            throw ValidationException::withMessages([
                'surplus' => 'لا يمكن حذف هذا الفائض.',
            ]);
        }

        $surplus->delete();
    }

    /**
     * @param  Collection<int, InstallmentSurplus>  $surpluses
     */
    public function returnMany(Collection $surpluses, Carbon|string|null $returnDate = null): int
    {
        $count = 0;

        DB::connection($this->connectionFor($surpluses))->transaction(function () use ($surpluses, $returnDate, &$count): void {
            foreach ($surpluses as $surplus) {
                if (! $surplus->status?->isOpen()) {
                    continue;
                }

                $this->returns->returnFromSurplus($surplus, $returnDate);
                $count++;
            }
        });

        return $count;
    }

    public function resolveContract(InstallmentSurplus $surplus): InstallmentContract|InstallmentContractArchive|null
    {
        $contractable = $surplus->contractable;

        if ($contractable instanceof InstallmentContract || $contractable instanceof InstallmentContractArchive) {
            return $contractable;
        }

        return null;
    }

    /**
     * @param  Collection<int, InstallmentSurplus>  $surpluses
     */
    protected function connectionFor(Collection $surpluses): ?string
    {
        return $surpluses->first()?->getConnectionName();
    }
}
