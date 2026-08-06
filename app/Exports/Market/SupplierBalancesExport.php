<?php

namespace App\Exports\Market;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\OurCompany;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SupplierBalancesExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    use RtlInstallmentSheet;

    private const HEADER_ROW = 8;

    private const DATA_START_ROW = 9;

    /**
     * @param  Collection<int, object>  $rows
     * @param  array{debit: float, credit: float, balance: float}  $summary
     */
    public function __construct(
        protected Collection $rows,
        protected ?OurCompany $company,
        protected string $reportTitle,
        protected array $summary,
    ) {}

    public function collection(): Collection
    {
        return collect();
    }

    public function map($row): array
    {
        return [
            Utf8Text::clean($row->name),
            (float) $row->mden,
            (float) $row->daen,
            (float) $row->raseed,
        ];
    }

    public function headings(): array
    {
        return [
            [Utf8Text::clean($this->company?->CompanyName)],
            [Utf8Text::clean($this->company?->CompanyNameSuffix)],
            [''],
            [''],
            [''],
            [''],
            [''],
            ['اسم المورد', 'مدين', 'دائن', 'الرصيد'],
        ];
    }

    public function registerEvents(): array
    {
        $rtlEvents = $this->rtlSheetEvents(
            headerRow: self::HEADER_ROW,
            columnCount: 4,
            columnWidths: [
                'A' => 50,
                'B' => 14,
                'C' => 14,
                'D' => 14,
            ],
            rightAlignedRows: [1, 2],
            centeredInfoRows: [5],
        );

        return [
            AfterSheet::class => function (AfterSheet $event) use ($rtlEvents): void {
                $rtlEvents[AfterSheet::class]($event);

                $sheet = $event->sheet->getDelegate();

                $sheet->mergeCells('A5:D5');
                $sheet->setCellValue('A5', $this->reportTitle);

                $rowIndex = self::DATA_START_ROW;

                foreach ($this->rows as $entry) {
                    $sheet->fromArray($this->map($entry), null, "A{$rowIndex}");
                    $rowIndex++;
                }

                $dataEndRow = $rowIndex - 1;

                if ($dataEndRow >= self::DATA_START_ROW) {
                    foreach (['B', 'C', 'D'] as $column) {
                        $sheet->getStyle("{$column}".self::DATA_START_ROW.":{$column}{$dataEndRow}")
                            ->getNumberFormat()
                            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                        $sheet->getStyle("{$column}".self::DATA_START_ROW.":{$column}{$dataEndRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }

                $totalRow = max(self::DATA_START_ROW, $dataEndRow + 1);
                $sheet->setCellValue("A{$totalRow}", 'الإجمالـــــــــي');
                $sheet->setCellValue("B{$totalRow}", (float) $this->summary['debit']);
                $sheet->setCellValue("C{$totalRow}", (float) $this->summary['credit']);
                $sheet->setCellValue("D{$totalRow}", (float) $this->summary['balance']);

                $sheet->getStyle("A{$totalRow}:D{$totalRow}")
                    ->getFont()
                    ->setBold(true);
                $sheet->getStyle("A{$totalRow}:D{$totalRow}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FF9DC1D3');

                foreach (['B', 'C', 'D'] as $column) {
                    $sheet->getStyle("{$column}{$totalRow}")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                    $sheet->getStyle("{$column}{$totalRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }
}
