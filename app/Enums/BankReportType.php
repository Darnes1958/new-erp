<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BankReportType: string implements HasLabel
{
    case NamesList = 'all';
    case Paid = 'mosdada';
    case Unpaid = 'not_mosdada';
    case Late = 'motakra';
    case Collected = 'mohasla';
    case Uncollected = 'not_mohasla';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NamesList => 'كشف بالأسماء',
            self::Paid => 'المسددة',
            self::Unpaid => 'لم تسدد بعد',
            self::Late => 'المتأخرة',
            self::Collected => 'المحصلة',
            self::Uncollected => 'الغير محصلة',
        };
    }

    public function usesDateRange(): bool
    {
        return match ($this) {
            self::Collected, self::Uncollected => true,
            default => false,
        };
    }

    public function usesThreshold(): bool
    {
        return match ($this) {
            self::Paid, self::Late => true,
            default => false,
        };
    }

    public function thresholdLabel(): string
    {
        return match ($this) {
            self::Paid => 'الباقي',
            self::Late => 'عدد الأقساط المتأخرة',
            default => '',
        };
    }

    public function defaultThreshold(): float
    {
        return match ($this) {
            self::Paid => 5,
            self::Late => 1,
            default => 0,
        };
    }

    public function isContractBased(): bool
    {
        return match ($this) {
            self::Collected => false,
            default => true,
        };
    }

    public function pdfTitle(?string $dateFrom = null, ?string $dateTo = null): string
    {
        $reportDate = now()->toDateString();

        return match ($this) {
            self::NamesList => "كشف بالعقود حتي تاريخ : {$reportDate}",
            self::Paid => "تقرير بالعقود المسددة حتي تاريخ : {$reportDate}",
            self::Unpaid => "تقرير بالعقود التي لم تسدد بعد حتي تاريخ : {$reportDate}",
            self::Late => "تقرير بالعقود المتاخرة السداد حتي تاريخ : {$reportDate}",
            self::Collected => "تقرير بالأقساط المحصلة من تاريخ : {$dateFrom} إلي : {$dateTo}",
            self::Uncollected => "تقرير بالأقساط الغير محصلة من تاريخ : {$dateFrom} إلي : {$dateTo}",
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel() ?? $case->value;
        }

        return $options;
    }
}
