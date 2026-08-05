<?php

namespace App\Services\Installments;

use App\Enums\InstallmentDeductionType;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentCancelledContract;
use App\Models\InstallmentDeduction;
use App\Models\SalesInvoice;
use App\Support\CompanySettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallmentContractMergeService
{
    public function __construct(
        protected InstallmentContractService $contracts,
        protected InstallmentContractMetricsService $metrics,
        protected InstallmentContractArchiveService $archives,
    ) {}

    public function merge(array $data): InstallmentContract
    {
        if (! CompanySettings::linkSalesToInstallments()) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => 'ضم العقد متاح فقط عند ربط العقود بفواتير المبيعات.',
            ]);
        }

        $invoice = SalesInvoice::query()->find($data['sales_invoice_id'] ?? null);

        if (! $invoice) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => 'يجب اختيار فاتورة مبيعات.',
            ]);
        }

        if ((int) $invoice->payment_method_id !== (int) config('erp.payment_method_codes.installment')) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => 'يجب أن تكون الفاتورة بيع تقسيط.',
            ]);
        }

        if ($invoice->hasInstallmentContract()) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => 'يوجد عقد مسبق لهذه الفاتورة.',
            ]);
        }

        $previousContract = $this->contracts->activeCustomerContract((int) $invoice->customer_id);

        if (! $previousContract) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => 'لا يوجد عقد قائم لهذا الزبون.',
            ]);
        }

        if ((int) ($data['previous_contract_id'] ?? 0) !== (int) $previousContract->id) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => 'تغيّر العقد القائم للزبون. أعد اختيار الفاتورة.',
            ]);
        }

        $contractId = (int) ($data['id'] ?? 0);

        if ($contractId <= 0) {
            throw ValidationException::withMessages([
                'id' => 'رقم العقد غير صالح.',
            ]);
        }

        if (
            InstallmentContract::query()->whereKey($contractId)->exists()
            || InstallmentContractArchive::query()->whereKey($contractId)->exists()
            || InstallmentCancelledContract::query()->whereKey($contractId)->exists()
        ) {
            throw ValidationException::withMessages([
                'id' => 'رقم العقد مستخدم مسبقاً.',
            ]);
        }

        $installmentCount = (int) ($data['installment_count'] ?? 0);

        if ($installmentCount <= 0) {
            throw ValidationException::withMessages([
                'installment_count' => 'عدد الأقساط مطلوب.',
            ]);
        }

        $previousContract->refresh();
        $previousBalance = (float) $previousContract->balance;

        if ($previousBalance <= 0) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => 'لا يوجد متبقٍ على العقد السابق لضمه.',
            ]);
        }

        $contractTotal = round((float) $invoice->balance + $previousBalance, 3);

        if ($contractTotal <= 0) {
            throw ValidationException::withMessages([
                'contract_total' => 'قيمة العقد يجب أن تكون أكبر من صفر.',
            ]);
        }

        $installmentAmount = (float) ($data['installment_amount'] ?? 0);

        if ($installmentAmount <= 0) {
            $installmentAmount = round($contractTotal / $installmentCount, 3);
        }

        [$installmentBankId, $payrollBankId] = $this->resolveBankIds($data, $previousContract);

        $contractStart = $data['contract_start'] ?? now()->toDateString();

        return DB::connection($previousContract->getConnectionName())->transaction(function () use (
            $data,
            $invoice,
            $previousContract,
            $contractId,
            $contractTotal,
            $installmentCount,
            $installmentAmount,
            $installmentBankId,
            $payrollBankId,
            $contractStart,
            $previousBalance,
        ): InstallmentContract {
            $previousContract->refresh();

            if (round((float) $previousContract->balance, 3) !== round($previousBalance, 3)) {
                throw ValidationException::withMessages([
                    'sales_invoice_id' => 'تغيّر رصيد العقد السابق. أعد المحاولة.',
                ]);
            }

            $this->createMergeClosingDeduction(
                $previousContract,
                $contractStart,
                $previousBalance,
                $contractId,
            );

            $this->metrics->recalculate($previousContract->refresh());

            $newContract = InstallmentContract::query()->create([
                'id' => $contractId,
                'customer_id' => (int) $previousContract->customer_id,
                'installment_bank_id' => $installmentBankId,
                'workplace_id' => $data['workplace_id'] ?? $previousContract->workplace_id,
                'payroll_bank_id' => $payrollBankId,
                'bank_account_number' => $data['bank_account_number'] ?? $previousContract->bank_account_number,
                'contract_start' => $contractStart,
                'contract_total' => $contractTotal,
                'installment_count' => $installmentCount,
                'installment_amount' => $installmentAmount,
                'total_paid' => 0,
                'balance' => $contractTotal,
                'sales_invoice_id' => $invoice->id,
                'cheques_in' => (int) ($data['cheques_in'] ?? 0),
                'cheques_out' => 0,
                'next_installment_date' => InstallmentContractMetricsService::initialNextInstallmentDate($contractStart),
                'late_amount' => 0,
                'installments_remaining' => $installmentCount,
                'surplus_count' => 0,
                'surplus_amount' => 0,
                'suspended_count' => 0,
                'suspended_amount' => 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->archives->moveFromActive($previousContract->refresh());

            return $newContract;
        });
    }

    protected function createMergeClosingDeduction(
        InstallmentContract $contract,
        string $deductionDate,
        float $amount,
        int $newContractId,
    ): InstallmentDeduction {
        return $contract->deductions()->create([
            'sequence' => ((int) $contract->deductions()->max('sequence')) + 1,
            'deducted_amount' => $amount,
            'deduction_date' => $deductionDate,
            'installment_due_date' => $this->nextDueDate($contract),
            'deduction_type_id' => InstallmentDeductionType::Bank->value,
            'notes' => 'قيمة تم ضمها للعقد رقم : '.$newContractId,
            'remaining_balance' => 0,
            'created_by' => Auth::id(),
        ]);
    }

    protected function nextDueDate(InstallmentContract $contract): string
    {
        $lastDueDate = $contract->deductions()->max('installment_due_date');

        if ($lastDueDate) {
            return InstallmentContractMetricsService::nextInstallmentDateAfter($lastDueDate);
        }

        return InstallmentContractMetricsService::initialNextInstallmentDate(
            $contract->contract_start ?? now()
        );
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected function resolveBankIds(array $data, InstallmentContract $previousContract): array
    {
        $installmentBankId = $data['installment_bank_id'] ?? $previousContract->installment_bank_id;
        $payrollBankId = $data['payroll_bank_id'] ?? $previousContract->payroll_bank_id;

        if ($installmentBankId && ! $payrollBankId) {
            $payrollBankId = \App\Models\InstallmentBank::query()
                ->whereKey($installmentBankId)
                ->value('payroll_bank_id');
        }

        return [$installmentBankId, $payrollBankId];
    }
}
