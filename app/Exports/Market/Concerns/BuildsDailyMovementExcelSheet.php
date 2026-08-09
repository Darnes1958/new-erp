<?php

namespace App\Exports\Market\Concerns;

use App\Models\OurCompany;
use App\Support\Utf8Text;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait BuildsDailyMovementExcelSheet
{
    protected function initializeDailyMovementSheet(
        Worksheet $sheet,
        ?OurCompany $company,
        string $reportTitle,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $warehouseName,
        int $columnCount,
    ): int {
        $sheet->setRightToLeft(true);
        $lastColumn = $this->excelColumnLetter($columnCount);

        $metaRows = [
            1 => Utf8Text::clean($company?->CompanyName),
            2 => Utf8Text::clean($company?->CompanyNameSuffix),
            4 => $reportTitle,
        ];

        if ($dateFrom || $dateTo) {
            $metaRows[5] = 'الفترة: '.($dateFrom ?? '—').' — '.($dateTo ?? '—');
        }

        if ($warehouseName) {
            $metaRows[6] = 'المخزن: '.Utf8Text::clean($warehouseName);
        }

        foreach ($metaRows as $row => $text) {
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $sheet->setCellValue("A{$row}", $text);
            $alignment = $sheet->getStyle("A{$row}")->getAlignment();
            $alignment->setVertical(Alignment::VERTICAL_CENTER);

            if ($row === 4) {
                $alignment->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
            } else {
                $alignment->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                if ($row === 1) {
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
                }
            }
        }

        return max(array_keys($metaRows)) + 2;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, float|int>  $columnWidths
     * @param  array<int, int>  $moneyColumnIndexes  1-based column indexes
     */
    protected function writeDailyMovementSection(
        Worksheet $sheet,
        int $startRow,
        string $title,
        array $headers,
        array $rows,
        array $columnWidths = [],
        array $moneyColumnIndexes = [],
    ): int {
        $columnCount = count($headers);
        $lastColumn = $this->excelColumnLetter($columnCount);

        $sheet->mergeCells("A{$startRow}:{$lastColumn}{$startRow}");
        $sheet->setCellValue("A{$startRow}", Utf8Text::clean($title));
        $sheet->getStyle("A{$startRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$startRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $headerRow = $startRow + 1;
        $sheet->fromArray($headers, null, "A{$headerRow}");
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

        $dataRow = $headerRow + 1;

        if ($rows === []) {
            $sheet->mergeCells("A{$dataRow}:{$lastColumn}{$dataRow}");
            $sheet->setCellValue("A{$dataRow}", 'لا توجد بيانات');
            $sheet->getStyle("A{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            return $dataRow + 2;
        }

        foreach ($rows as $rowValues) {
            $sheet->fromArray($rowValues, null, "A{$dataRow}");
            $dataRow++;
        }

        $dataEndRow = $dataRow - 1;

        foreach ($moneyColumnIndexes as $columnIndex) {
            $column = $this->excelColumnLetter($columnIndex);
            $sheet->getStyle("{$column}{$headerRow}:{$column}{$dataEndRow}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $sheet->getStyle("{$column}".($headerRow + 1).":{$column}{$dataEndRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth((float) $width);
        }

        return $dataEndRow + 2;
    }

    protected function excelColumnLetter(int $columnIndex): string
    {
        $letter = '';

        while ($columnIndex > 0) {
            $columnIndex--;
            $letter = chr(65 + ($columnIndex % 26)).$letter;
            $columnIndex = intdiv($columnIndex, 26);
        }

        return $letter;
    }
}
