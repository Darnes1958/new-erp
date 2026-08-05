<?php

namespace App\Services\Installments;

use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentDeduction;
use App\Models\InstallmentDeductionArchive;
use App\Models\InstallmentSurplus;
use App\Models\InstallmentSuspended;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallmentContractArchiveService
{
    public function moveFromActive(InstallmentContract $contract): InstallmentContractArchive
    {
        $connection = $contract->getConnectionName();

        return DB::connection($connection)->transaction(function () use ($contract, $connection): InstallmentContractArchive {
            if (InstallmentContractArchive::on($connection)->whereKey($contract->id)->exists()) {
                throw ValidationException::withMessages([
                    'id' => 'العقد موجود مسبقاً في الأرشيف.',
                ]);
            }

            $attributes = $contract->only([
                'id',
                'customer_id',
                'installment_bank_id',
                'workplace_id',
                'payroll_bank_id',
                'bank_account_number',
                'contract_start',
                'contract_end',
                'contract_total',
                'installment_count',
                'installment_amount',
                'total_paid',
                'balance',
                'sales_invoice_id',
                'cheques_in',
                'cheques_out',
                'notes',
                'created_by',
            ]);

            $archive = InstallmentContractArchive::on($connection)->create([
                ...$attributes,
                'archived_at' => now()->toDateString(),
            ]);

            $contract->deductions()
                ->orderBy('id')
                ->each(function (InstallmentDeduction $deduction) use ($archive, $connection): void {
                    InstallmentDeductionArchive::on($connection)->create([
                        'installment_contract_id' => $archive->id,
                        'sequence' => $deduction->sequence,
                        'deducted_amount' => $deduction->deducted_amount,
                        'deduction_date' => $deduction->deduction_date,
                        'installment_due_date' => $deduction->installment_due_date,
                        'deduction_type_id' => $deduction->deduction_type_id,
                        'notes' => $deduction->notes,
                        'batch_id' => $deduction->batch_id,
                        'remaining_balance' => $deduction->remaining_balance,
                        'created_by' => $deduction->created_by,
                        'created_at' => $deduction->created_at,
                        'updated_at' => $deduction->updated_at,
                    ]);

                    $deduction->delete();
                });

            InstallmentSurplus::on($connection)
                ->where('contractable_type', $contract->getMorphClass())
                ->where('contractable_id', $contract->id)
                ->update([
                    'contractable_type' => $archive->getMorphClass(),
                ]);

            InstallmentSuspended::on($connection)
                ->where('contractable_type', $contract->getMorphClass())
                ->where('contractable_id', $contract->id)
                ->update([
                    'contractable_type' => $archive->getMorphClass(),
                ]);

            InstallmentSuspended::on($connection)
                ->where('installment_contract_id', $contract->id)
                ->update([
                    'installment_contract_id' => null,
                ]);

            DB::connection($connection)
                ->table('installment_stops')
                ->where('installment_contract_id', $contract->id)
                ->delete();

            DB::connection($connection)
                ->table('installment_cheques')
                ->where('installment_contract_id', $contract->id)
                ->delete();

            $contract->delete();

            return $archive->refresh();
        });
    }
}
