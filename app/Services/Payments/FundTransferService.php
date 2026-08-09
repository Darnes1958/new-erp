<?php

namespace App\Services\Payments;

use App\Enums\FundTransferKind;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class FundTransferService
{
    public function prepareAttributes(array $data): array
    {
        $kind = FundTransferKind::from((int) ($data['transfer_kind'] ?? 0));
        $amount = (float) ($data['amount'] ?? 0);

        if ($amount <= 0) {
            throw new RuntimeException('المبلغ يجب أن يكون أكبر من صفر.');
        }

        $data['from_cash_box_id'] = $kind->usesFromCashBox()
            ? ($data['from_cash_box_id'] ?? null)
            : null;
        $data['to_cash_box_id'] = $kind->usesToCashBox()
            ? ($data['to_cash_box_id'] ?? null)
            : null;
        $data['from_bank_account_id'] = $kind->usesFromBankAccount()
            ? ($data['from_bank_account_id'] ?? null)
            : null;
        $data['to_bank_account_id'] = $kind->usesToBankAccount()
            ? ($data['to_bank_account_id'] ?? null)
            : null;

        if ($kind->usesFromCashBox() && empty($data['from_cash_box_id'])) {
            throw new RuntimeException('اختر الخزينة المصدر.');
        }

        if ($kind->usesToCashBox() && empty($data['to_cash_box_id'])) {
            throw new RuntimeException('اختر الخزينة الوجهة.');
        }

        if ($kind->usesFromBankAccount() && empty($data['from_bank_account_id'])) {
            throw new RuntimeException('اختر الحساب المصرفي المصدر.');
        }

        if ($kind->usesToBankAccount() && empty($data['to_bank_account_id'])) {
            throw new RuntimeException('اختر الحساب المصرفي الوجهة.');
        }

        if ($kind === FundTransferKind::CashToCash
            && (int) $data['from_cash_box_id'] === (int) $data['to_cash_box_id']) {
            throw new RuntimeException('لا يمكن التحويل إلى نفس الخزينة.');
        }

        if ($kind === FundTransferKind::BankToBank
            && (int) $data['from_bank_account_id'] === (int) $data['to_bank_account_id']) {
            throw new RuntimeException('لا يمكن التحويل إلى نفس الحساب المصرفي.');
        }

        $data['payment_method_id'] = $kind->paymentMethodId();
        $data['created_by'] ??= Auth::id();

        return $data;
    }
}
