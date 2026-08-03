<?php

namespace App\Exports\Installments\Concerns;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

trait RtlInstallmentSheet
{
    /**
     * @return array<class-string, callable>
     */
    protected function rtlSheetEvents(int $headerRow, int $columnCount): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) use ($headerRow, $columnCount): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft(true);

                $lastColumn = chr(ord('A') + max(0, $columnCount - 1));

                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $infoEndRow = max(2, $headerRow - 2);

                for ($row = 2; $row <= $infoEndRow; $row++) {
                    $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")
                    ->getFont()
                    ->setBold(true);
                $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FF9DC1D3');
                $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
