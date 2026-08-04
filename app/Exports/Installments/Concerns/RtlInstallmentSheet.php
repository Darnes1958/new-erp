<?php

namespace App\Exports\Installments\Concerns;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

trait RtlInstallmentSheet
{
    /**
     * @param  array<string, float|int>  $columnWidths
     * @return array<class-string, callable>
     */
    protected function rtlSheetEvents(
        int $headerRow,
        int $columnCount,
        array $columnWidths = [],
        ?float $totalAmount = null,
        string $totalLabel = 'الإجمالي',
        array $rightAlignedRows = [],
        array $centeredInfoRows = [],
    ): array {
        return [
            AfterSheet::class => function (AfterSheet $event) use ($headerRow, $columnCount, $columnWidths, $totalAmount, $totalLabel, $rightAlignedRows, $centeredInfoRows): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft(true);

                $lastColumn = chr(ord('A') + max(0, $columnCount - 1));

                if ($rightAlignedRows === [] && $centeredInfoRows === []) {
                    $infoEndRow = max(1, $headerRow - 2);

                    for ($row = 1; $row <= $infoEndRow; $row++) {
                        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
                        $style = $sheet->getStyle("A{$row}");
                        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                        if ($row === 1) {
                            $style->getFont()->setBold(true)->setSize(14);
                        }
                    }
                } else {
                    foreach ($rightAlignedRows as $row) {
                        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
                        $style = $sheet->getStyle("A{$row}");
                        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                        if ($row === 1) {
                            $style->getFont()->setBold(true)->setSize(14);
                        }
                    }

                    foreach ($centeredInfoRows as $row) {
                        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
                        $style = $sheet->getStyle("A{$row}");
                        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                        $style->getFont()->setBold(true);
                    }

                    $firstTitleRow = $centeredInfoRows[0] ?? null;

                    if ($firstTitleRow !== null) {
                        for ($spacerRow = max(1, $firstTitleRow - 2); $spacerRow < $firstTitleRow; $spacerRow++) {
                            $sheet->getRowDimension($spacerRow)->setRowHeight(18);
                        }
                    }
                }

                foreach ($columnWidths as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth((float) $width);
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

                $dataEndRow = $sheet->getHighestRow();

                if ($dataEndRow > $headerRow && $columnCount === 4) {
                    $sheet->getStyle('B'.($headerRow + 1).":B{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('C'.($headerRow + 1).":C{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('D'.($headerRow + 1).":D{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                if ($totalAmount !== null && $columnCount >= 4) {
                    $totalRow = $dataEndRow + 1;
                    $labelColumn = chr(ord('A') + $columnCount - 2);
                    $amountColumn = chr(ord('A') + $columnCount - 1);

                    $sheet->setCellValue("{$labelColumn}{$totalRow}", $totalLabel);
                    $sheet->setCellValue("{$amountColumn}{$totalRow}", $totalAmount);
                    $sheet->getStyle("{$labelColumn}{$totalRow}:{$amountColumn}{$totalRow}")
                        ->getFont()
                        ->setBold(true);
                    $sheet->getStyle("{$labelColumn}{$totalRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("{$amountColumn}{$totalRow}")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                    $sheet->getStyle("{$amountColumn}{$totalRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }
}
