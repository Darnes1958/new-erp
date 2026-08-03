<?php

namespace App\Services\Installments;

use App\Models\InstallmentBank;
use App\Models\InstallmentCancelledContract;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentDeduction;
use App\Models\InstallmentSurplus;
use App\Models\InstallmentSuspended;
use App\Models\SalesInvoice;
use App\Services\Installments\InstallmentContractMetricsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallmentContractService
{
    public function __construct(
        protected InstallmentContractMetricsService $metrics,
    ) {}

    public static function nextContractId(): int
    {
        $activeMax = (int) (InstallmentContract::query()->max('id') ?? 0);
        $archiveMax = (int) (InstallmentContractArchive::query()->max('id') ?? 0);
        $cancelledMax = (int) (InstallmentCancelledContract::query()->max('id') ?? 0);

        return max($activeMax, $archiveMax, $cancelledMax) + 1;
    }

    public static function eligibleSalesInvoicesQuery()
    {
        $installmentPaymentId = (int) config('erp.payment_method_codes.installment');

        return SalesInvoice::query()
            ->where('payment_method_id', $installmentPaymentId)
            ->whereDoesntHave('installmentContract')
            ->whereDoesntHave('installmentContractArchive')
            ->with('customer');
    }

    public static function eligibleSalesInvoicesQueryForEdit(int $currentInvoiceId)
    {
        $installmentPaymentId = (int) config('erp.payment_method_codes.installment');

        return SalesInvoice::query()
            ->where('payment_method_id', $installmentPaymentId)
            ->where(function ($query) use ($currentInvoiceId): void {
                $query->where(function ($eligible): void {
                    $eligible->whereDoesntHave('installmentContract')
                        ->whereDoesntHave('installmentContractArchive');
                })->orWhere('id', $currentInvoiceId);
            })
            ->with('customer');
    }

    public function create(array $data): InstallmentContract
    {
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

        $contractTotal = (float) ($data['contract_total'] ?? $invoice->balance);

        if ($contractTotal <= 0) {
            throw ValidationException::withMessages([
                'contract_total' => 'قيمة العقد يجب أن تكون أكبر من صفر.',
            ]);
        }

        $installmentAmount = (float) ($data['installment_amount'] ?? 0);

        if ($installmentAmount <= 0) {
            $installmentAmount = round($contractTotal / $installmentCount, 3);
        }

        $installmentBankId = $data['installment_bank_id'] ?? null;
        $payrollBankId = $data['payroll_bank_id'] ?? null;

        if ($installmentBankId && ! $payrollBankId) {
            $payrollBankId = InstallmentBank::query()
                ->whereKey($installmentBankId)
                ->value('payroll_bank_id');
        }

        $contract = InstallmentContract::query()->create([
            'id' => $contractId,
            'customer_id' => (int) ($data['customer_id'] ?? $invoice->customer_id),
            'installment_bank_id' => $installmentBankId,
            'workplace_id' => $data['workplace_id'] ?? null,
            'payroll_bank_id' => $payrollBankId,
            'bank_account_number' => $data['bank_account_number'] ?? null,
            'contract_start' => $data['contract_start'] ?? now()->toDateString(),
            'contract_total' => $contractTotal,
            'installment_count' => $installmentCount,
            'installment_amount' => $installmentAmount,
            'total_paid' => 0,
            'balance' => $contractTotal,
            'sales_invoice_id' => $invoice->id,
            'cheques_in' => (int) ($data['cheques_in'] ?? 0),
            'cheques_out' => 0,
            'next_installment_date' => InstallmentContractMetricsService::initialNextInstallmentDate(
                $data['contract_start'] ?? now()->toDateString()
            ),
            'late_amount' => 0,
            'installments_remaining' => $installmentCount,
            'surplus_count' => 0,
            'surplus_amount' => 0,
            'suspended_count' => 0,
            'suspended_amount' => 0,
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return $contract;
    }

    public function previousCustomerContract(int $customerId): ?InstallmentContract
    {
        return InstallmentContract::query()
            ->where('customer_id', $customerId)
            ->orderBy('contract_start')
            ->first();
    }

    public function updateLinked(InstallmentContract $contract, array $data): InstallmentContract
    {
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

        if (
            (int) $invoice->id !== (int) $contract->sales_invoice_id
            && $invoice->hasInstallmentContract()
        ) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => 'يوجد عقد مسبق لهذه الفاتورة.',
            ]);
        }

        $contractId = (int) ($data['id'] ?? $contract->id);
        $this->assertContractIdAvailable($contractId, $contract->id);

        $installmentCount = (int) ($data['installment_count'] ?? 0);

        if ($installmentCount <= 0) {
            throw ValidationException::withMessages([
                'installment_count' => 'عدد الأقساط مطلوب.',
            ]);
        }

        $contractTotal = (float) ($data['contract_total'] ?? $invoice->balance);

        if ($contractTotal <= 0) {
            throw ValidationException::withMessages([
                'contract_total' => 'قيمة العقد يجب أن تكون أكبر من صفر.',
            ]);
        }

        $installmentAmount = (float) ($data['installment_amount'] ?? 0);

        if ($installmentAmount <= 0) {
            $installmentAmount = round($contractTotal / $installmentCount, 3);
        }

        [$installmentBankId, $payrollBankId] = $this->resolveBankIds($data);

        $attributes = [
            'customer_id' => (int) ($data['customer_id'] ?? $invoice->customer_id),
            'installment_bank_id' => $installmentBankId,
            'workplace_id' => $data['workplace_id'] ?? null,
            'payroll_bank_id' => $payrollBankId,
            'bank_account_number' => $data['bank_account_number'] ?? null,
            'contract_start' => $data['contract_start'] ?? $contract->contract_start,
            'contract_total' => $contractTotal,
            'installment_count' => $installmentCount,
            'installment_amount' => $installmentAmount,
            'sales_invoice_id' => $invoice->id,
            'cheques_in' => (int) ($data['cheques_in'] ?? 0),
            'notes' => $data['notes'] ?? null,
        ];

        if ($contractId !== (int) $contract->id) {
            $attributes['id'] = $contractId;
        }

        $contract->fill($attributes);
        $contract->save();

        return $contract->refresh();
    }

    public function updateStandalone(InstallmentContract $contract, array $data): InstallmentContract
    {
        $contractId = (int) ($data['id'] ?? $contract->id);
        $this->assertContractIdAvailable($contractId, $contract->id);

        $installmentCount = (int) ($data['installment_count'] ?? 0);

        if ($installmentCount <= 0) {
            throw ValidationException::withMessages([
                'installment_count' => 'عدد الأقساط مطلوب.',
            ]);
        }

        $contractTotal = (float) ($data['contract_total'] ?? 0);

        if ($contractTotal <= 0) {
            throw ValidationException::withMessages([
                'contract_total' => 'قيمة العقد يجب أن تكون أكبر من صفر.',
            ]);
        }

        $installmentAmount = (float) ($data['installment_amount'] ?? 0);

        if ($installmentAmount <= 0) {
            $installmentAmount = round($contractTotal / $installmentCount, 3);
        }

        [$installmentBankId, $payrollBankId] = $this->resolveBankIds($data);

        $attributes = [
            'customer_id' => (int) ($data['customer_id'] ?? $contract->customer_id),
            'installment_bank_id' => $installmentBankId,
            'workplace_id' => $data['workplace_id'] ?? null,
            'payroll_bank_id' => $payrollBankId,
            'bank_account_number' => $data['bank_account_number'] ?? null,
            'contract_start' => $data['contract_start'] ?? $contract->contract_start,
            'contract_total' => $contractTotal,
            'installment_count' => $installmentCount,
            'installment_amount' => $installmentAmount,
            'cheques_in' => (int) ($data['cheques_in'] ?? 0),
            'notes' => $data['notes'] ?? null,
        ];

        if ($contractId !== (int) $contract->id) {
            $attributes['id'] = $contractId;
        }

        $contract->fill($attributes);
        $contract->save();

        return $contract->refresh();
    }

    public function cancelAfterContract(InstallmentContract $contract): InstallmentCancelledContract
    {
        return app(InstallmentCancelledContractService::class)->moveFromActive($contract);
    }

    public function cancel(InstallmentContract $contract): void
    {
        DB::connection($contract->getConnectionName())->transaction(function () use ($contract): void {
            InstallmentDeduction::query()
                ->where('installment_contract_id', $contract->id)
                ->delete();

            $contract->surpluses()->delete();
            $contract->suspendedEntries()->delete();

            DB::connection($contract->getConnectionName())
                ->table('installment_stops')
                ->where('installment_contract_id', $contract->id)
                ->delete();

            DB::connection($contract->getConnectionName())
                ->table('installment_cheques')
                ->where('installment_contract_id', $contract->id)
                ->delete();

            $contract->delete();
        });
    }

    protected function assertContractIdAvailable(int $contractId, int|string $currentId): void
    {
        if ($contractId <= 0) {
            throw ValidationException::withMessages([
                'id' => 'رقم العقد غير صالح.',
            ]);
        }

        if ((string) $contractId === (string) $currentId) {
            return;
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
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected function resolveBankIds(array $data): array
    {
        $installmentBankId = $data['installment_bank_id'] ?? null;
        $payrollBankId = $data['payroll_bank_id'] ?? null;

        if ($installmentBankId && ! $payrollBankId) {
            $payrollBankId = InstallmentBank::query()
                ->whereKey($installmentBankId)
                ->value('payroll_bank_id');
        }

        return [$installmentBankId, $payrollBankId];
    }
}
