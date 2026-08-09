<?php

namespace App\Support\Conversion;

enum LegacySchemaKind: string
{
    case Ins = 'ins';
    case Erp = 'erp';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Ins => 'INS (main / kst_trans / jeha)',
            self::Erp => 'ERP (mains / trans / customers)',
            self::Unknown => 'غير معروف',
        };
    }
}
