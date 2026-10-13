<?php

namespace App\Exports\Market\Concerns;

use App\Support\ErpNumber;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait ColorsSignedExcelBalances
{
    /**
     * @param  list<array{label: string, value: float, signed?: bool}>  $parts
     */
    protected function signedSummaryRichText(array $parts): RichText
    {
        $rich = new RichText();
        $first = true;

        foreach ($parts as $part) {
            if (! $first) {
                $rich->createText('    ');
            }

            $first = false;
            $rich->createText($part['label']);
            $run = $rich->createTextRun(ErpNumber::money($part['value']));
            $signed = $part['signed'] ?? true;

            if ($signed) {
                $argb = ErpNumber::signedExcelArgb($part['value']);

                if ($argb !== null) {
                    $run->getFont()->getColor()->setARGB($argb);
                    $run->getFont()->setBold(true);
                }
            }
        }

        return $rich;
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    protected function colorRunningBalanceColumn(Worksheet $sheet, Collection $rows, string $column, int $startRow): void
    {
        $colorRow = $startRow;

        foreach ($rows as $entry) {
            $balanceArgb = ErpNumber::signedExcelArgb($entry->running_balance ?? 0);

            if ($balanceArgb !== null) {
                $sheet->getStyle("{$column}{$colorRow}")->getFont()->getColor()->setARGB($balanceArgb);
            }

            $colorRow++;
        }
    }
}
