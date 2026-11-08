<?php

namespace App\Support\Conversion;

/**
 * INS price_type / tran_type numbers are reversed vs ERP / InsFila:
 *   INS 2 = تقسيط,  INS 3 = صك
 *   ERP 2 = صك/مصرفي, ERP 3 = تقسيط
 */
final class InsPaymentMethodMap
{
    public const ERP_CASH = 1;

    public const ERP_BANK = 2;

    public const ERP_INSTALLMENT = 3;

    public const INS_CASH = 1;

    public const INS_INSTALLMENT = 2;

    public const INS_CHEQUE = 3;

    public static function toErp(mixed $insTypeNo): int
    {
        return match ((int) $insTypeNo) {
            self::INS_INSTALLMENT => self::ERP_INSTALLMENT,
            self::INS_CHEQUE => self::ERP_BANK,
            self::INS_CASH => self::ERP_CASH,
            default => (int) $insTypeNo,
        };
    }
}
