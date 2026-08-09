<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SystemOperationAction: string implements HasColor, HasLabel
{
    case Update = 'update';
    case Cancel = 'cancel';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Update => 'تعديل',
            self::Cancel => 'إلغاء',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Update => 'warning',
            self::Cancel => 'danger',
        };
    }

    public static function tryFromLegacy(string $value): ?self
    {
        $normalized = trim($value);

        return match ($normalized) {
            'تعديل', 'نعديل' => self::Update,
            'الغاء', 'إلغاء', 'ارجاع عقد ملغي', 'الغاء بعد التعاقد' => self::Cancel,
            default => null,
        };
    }
}
