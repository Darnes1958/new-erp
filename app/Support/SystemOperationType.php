<?php

namespace App\Support;

class SystemOperationType
{
    public const PURCHASE = 'purchase';

    public const SALE = 'sale';

    public const PURCHASE_RETURN = 'purchase_return';

    public const SALE_RETURN = 'sale_return';

    public const CUSTOMER_RECEIPT = 'customer_receipt';

    public const SUPPLIER_PAYMENT = 'supplier_payment';

    public const INSTALLMENT_CONTRACT = 'installment_contract';

    public const INSTALLMENT_DEDUCTION = 'installment_deduction';

    public const INSTALLMENT_SURPLUS = 'installment_surplus';

    public const INSTALLMENT_RETURN = 'installment_return';

    public const WRONG_DEDUCTION = 'wrong_deduction';

    public const DEDUCTION_BATCH = 'deduction_batch';

    public const FUND_TRANSFER = 'fund_transfer';

    public const WAREHOUSE_TRANSFER = 'warehouse_transfer';

    public const ITEM = 'item';

    public const ITEM_PRICE = 'item_price';

    public const EXPENSE = 'expense';

    public const SALARY = 'salary';

    public const SALARY_TRANSACTION = 'salary_transaction';

    public const RENT_TRANSACTION = 'rent_transaction';

    public const BANK_ACCOUNT = 'bank_account';

    public const CASH_BOX = 'cash_box';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::PURCHASE => 'مشتريات',
            self::SALE => 'مبيعات',
            self::PURCHASE_RETURN => 'ترجيع مشتريات',
            self::SALE_RETURN => 'ترجيع مبيعات',
            self::CUSTOMER_RECEIPT => 'ايصال زبائن',
            self::SUPPLIER_PAYMENT => 'ايصال موردين',
            self::INSTALLMENT_CONTRACT => 'عقود',
            self::INSTALLMENT_DEDUCTION => 'خصومات',
            self::INSTALLMENT_SURPLUS => 'فائض',
            self::INSTALLMENT_RETURN => 'ترجيع اقساط',
            self::WRONG_DEDUCTION => 'بالخطأ',
            self::DEDUCTION_BATCH => 'حافظة',
            self::FUND_TRANSFER => 'تحويل',
            self::WAREHOUSE_TRANSFER => 'تحويل مخزن',
            self::ITEM => 'اصناف',
            self::ITEM_PRICE => 'اسعار صنف',
            self::EXPENSE => 'مصروفات',
            self::SALARY => 'مرتبات',
            self::SALARY_TRANSACTION => 'حركة مرتب',
            self::RENT_TRANSACTION => 'حركة ايجار',
            self::BANK_ACCOUNT => 'مصارف',
            self::CASH_BOX => 'خزائن',
        ];
    }

    public static function label(string $operation): string
    {
        return self::labels()[$operation] ?? $operation;
    }

    /**
     * @return list<string>
     */
    public static function withCustomerContext(): array
    {
        return [
            self::SALE,
            self::SALE_RETURN,
            self::CUSTOMER_RECEIPT,
            self::INSTALLMENT_CONTRACT,
            self::INSTALLMENT_DEDUCTION,
            self::INSTALLMENT_SURPLUS,
            self::INSTALLMENT_RETURN,
        ];
    }

    /**
     * @return list<string>
     */
    public static function withItemContext(): array
    {
        return [
            self::ITEM,
            self::ITEM_PRICE,
            self::PURCHASE_RETURN,
            self::SALE_RETURN,
            self::WAREHOUSE_TRANSFER,
        ];
    }

    public static function tryFromLegacy(string $value): string
    {
        $normalized = trim($value);

        foreach (self::labels() as $key => $label) {
            if ($label === $normalized) {
                return $key;
            }
        }

        return match ($normalized) {
            'ايصال' => self::CUSTOMER_RECEIPT,
            'نرجيع اقساط' => self::INSTALLMENT_RETURN,
            'عقد' => self::INSTALLMENT_CONTRACT,
            'قسط' => self::INSTALLMENT_DEDUCTION,
            'سحب مرتب' => self::SALARY_TRANSACTION,
            default => str($normalized)->slug('_')->toString(),
        };
    }
}
