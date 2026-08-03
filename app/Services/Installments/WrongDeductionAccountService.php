<?php

namespace App\Services\Installments;

use App\Models\DeductionBatch;
use App\Models\WrongDeductionAccount;
use Illuminate\Support\Facades\Auth;

class WrongDeductionAccountService
{
    public function remember(
        DeductionBatch $batch,
        string $accountNumber,
        string $name,
    ): WrongDeductionAccount {
        $accountNumber = trim($accountNumber);
        $name = trim($name);

        return WrongDeductionAccount::on($batch->getConnectionName())->updateOrCreate(
            [
                'payroll_bank_id' => $batch->payroll_bank_id,
                'account_number' => $accountNumber,
            ],
            [
                'installment_bank_id' => $batch->installment_bank_id,
                'name' => $name,
                'created_by' => Auth::id(),
            ],
        );
    }

    public function findForBatch(DeductionBatch $batch, string $accountNumber): ?WrongDeductionAccount
    {
        $accountNumber = trim($accountNumber);

        if ($accountNumber === '') {
            return null;
        }

        return WrongDeductionAccount::on($batch->getConnectionName())
            ->where('payroll_bank_id', $batch->payroll_bank_id)
            ->where('account_number', $accountNumber)
            ->first();
    }

    public function find(
        ?string $connection,
        ?int $payrollBankId,
        ?int $installmentBankId,
        string $accountNumber,
    ): ?WrongDeductionAccount {
        $accountNumber = trim($accountNumber);

        if ($accountNumber === '' || ! $payrollBankId) {
            return null;
        }

        $query = $connection
            ? WrongDeductionAccount::on($connection)->newQuery()
            : WrongDeductionAccount::query();

        return $query
            ->where('payroll_bank_id', $payrollBankId)
            ->when(
                $installmentBankId,
                fn ($q) => $q->where(function ($inner) use ($installmentBankId): void {
                    $inner->whereNull('installment_bank_id')
                        ->orWhere('installment_bank_id', $installmentBankId);
                }),
            )
            ->where('account_number', $accountNumber)
            ->first();
    }
}
