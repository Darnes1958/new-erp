<?php

namespace App\Services\Installments;

use App\Enums\DeductionBatchEntryType;
use App\Enums\DeductionBatchPostedType;
use App\Models\DeductionBatch;
use App\Models\DeductionBatchLine;
use App\Support\ErpNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

final class DeductionBatchTypeBreakdown
{
    /**
     * @return list<array{type: DeductionBatchPostedType, label: string, color: string, count: int, total: float}>
     */
    public static function forBatch(DeductionBatch $batch): array
    {
        $rows = DeductionBatchLine::query()
            ->where('deduction_batch_id', $batch->id)
            ->selectRaw('entry_type, posted_type, COUNT(*) AS cnt, SUM(amount) AS total')
            ->groupBy('entry_type', 'posted_type')
            ->get();

        return static::fromAggregates($rows);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array{type: DeductionBatchPostedType, label: string, color: string, count: int, total: float}>
     */
    public static function fromAggregates(Collection $rows): array
    {
        $buckets = [];

        foreach (DeductionBatchPostedType::cases() as $type) {
            $buckets[$type->value] = [
                'type' => $type,
                'label' => $type->getLabel() ?? $type->name,
                'color' => $type->viewerColor(),
                'count' => 0,
                'total' => 0.0,
            ];
        }

        foreach ($rows as $row) {
            $posted = $row->posted_type instanceof DeductionBatchPostedType
                ? $row->posted_type
                : DeductionBatchPostedType::tryFrom((int) ($row->posted_type ?? 0));
            $entry = $row->entry_type instanceof DeductionBatchEntryType
                ? $row->entry_type
                : DeductionBatchEntryType::tryFrom((int) ($row->entry_type ?? 0));
            $type = static::displayType($posted, $entry);

            if ($type === null) {
                continue;
            }

            $buckets[$type->value]['count'] += (int) $row->cnt;
            $buckets[$type->value]['total'] += (float) $row->total;
        }

        return array_values(array_filter(
            $buckets,
            fn (array $bucket): bool => $bucket['count'] > 0,
        ));
    }

    public static function displayType(?DeductionBatchPostedType $posted, ?DeductionBatchEntryType $entry): ?DeductionBatchPostedType
    {
        if ($posted !== null) {
            return $posted;
        }

        return match ($entry) {
            DeductionBatchEntryType::Active => DeductionBatchPostedType::Normal,
            DeductionBatchEntryType::Archive => DeductionBatchPostedType::Archive,
            DeductionBatchEntryType::Wrong => DeductionBatchPostedType::Wrong,
            DeductionBatchEntryType::Cancelled => DeductionBatchPostedType::Cancelled,
            default => null,
        };
    }

    /**
     * @param  list<array{type: DeductionBatchPostedType, label: string, color: string, count: int, total: float}>  $items
     */
    public static function html(array $items): HtmlString
    {
        if ($items === []) {
            return new HtmlString('<div class="text-sm text-gray-500">لا توجد أقساط مدخلة بعد</div>');
        }

        $chips = '';
        $allCount = 0;
        $allTotal = 0.0;

        foreach ($items as $item) {
            $allCount += $item['count'];
            $allTotal += $item['total'];
            $chips .= static::chipHtml(
                $item['label'],
                $item['count'],
                $item['total'],
                $item['color'],
            );
        }

        $chips .= static::chipHtml('الإجمالي', $allCount, $allTotal, '#1e3a8a', bold: true);

        return new HtmlString(
            '<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:stretch;">'.$chips.'</div>'
        );
    }

    protected static function chipHtml(string $label, int $count, float $total, string $color, bool $bold = false): string
    {
        $weight = $bold ? '700' : '600';

        return '<div style="border:1px solid '.$color.';border-radius:8px;padding:6px 10px;min-width:7.5rem;background:#fff;">'
            .'<div style="color:'.$color.';font-weight:'.$weight.';font-size:13px;">'.e($label).'</div>'
            .'<div style="font-size:12px;margin-top:2px;">العدد: <b>'.$count.'</b></div>'
            .'<div style="font-size:12px;">الإجمالي: <b>'.e(ErpNumber::format($total, 0)).'</b></div>'
            .'</div>';
    }
}
