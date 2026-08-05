<?php

namespace App\Exports\Installments;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\InstallmentBank;
use App\Models\OurCompany;
use App\Models\PayrollBank;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class BankTotalsExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    use RtlInstallmentSheet;

    /** @var array<string, mixed>|null */
    protected ?array $headerLayout = null;

    /**
     * @param  Collection<int, InstallmentBank|PayrollBank>  $rows
     */
    public function __construct(
        protected Collection $rows,
        protected int $filterBy,
        protected ?OurCompany $company,
        protected string $reportTitle,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @param  InstallmentBank|PayrollBank  $row
     * @return array<int, string|int|float|null>
     */
    public function map($row): array
    {
        return [
            $row->id,
            Utf8Text::clean($row->name),
            (int) ($row->contracts_count ?? 0),
            (float) ($row->contracts_total ?? 0),
            (float) ($row->total_paid ?? 0),
            (float) ($row->balance_total ?? 0),
            (float) ($row->surplus_total ?? 0),
            (float) ($row->suspended_total ?? 0),
            (float) ($row->wrong_total ?? 0),
        ];
    }

    public function headings(): array
    {
        return $this->headerLayout()['headings'];
    }

    public function registerEvents(): array
    {
        $layout = $this->headerLayout();
        $rtlEvents = $this->rtlSheetEvents(
            headerRow: $layout['headerRow'],
            columnCount: 9,
            columnWidths: [
                'A' => 12,
                'B' => 32,
                'C' => 14,
                'D' => 14,
                'E' => 14,
                'F' => 14,
                'G' => 14,
                'H' => 14,
                'I' => 14,
            ],
            rightAlignedRows: $layout['rightAlignedRows'],
            centeredInfoRows: $layout['centeredInfoRows'],
        );

        $rtlCallback = $rtlEvents[AfterSheet::class];
        $totals = $this->totals();

        return [
            AfterSheet::class => function (AfterSheet $event) use ($rtlCallback, $layout, $totals): void {
                $rtlCallback($event);

                $sheet = $event->sheet->getDelegate();
                $headerRow = $layout['headerRow'];
                $dataEndRow = $sheet->getHighestRow();

                if ($dataEndRow > $headerRow) {
                    $dataRange = 'A'.($headerRow + 1).":I{$dataEndRow}";

                    $sheet->getStyle($dataRange)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle('A'.($headerRow + 1).":A{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                if ($dataEndRow <= $headerRow) {
                    return;
                }

                $totalRow = $dataEndRow + 1;
                $sheet->setCellValue("B{$totalRow}", 'الإجمالي');
                $sheet->setCellValue("C{$totalRow}", $totals['contracts_count']);
                $sheet->setCellValue("D{$totalRow}", $totals['contracts_total']);
                $sheet->setCellValue("E{$totalRow}", $totals['total_paid']);
                $sheet->setCellValue("F{$totalRow}", $totals['balance_total']);
                $sheet->setCellValue("G{$totalRow}", $totals['surplus_total']);
                $sheet->setCellValue("H{$totalRow}", $totals['suspended_total']);
                $sheet->setCellValue("I{$totalRow}", $totals['wrong_total']);

                foreach (['D', 'E', 'F', 'G', 'H', 'I'] as $columnLetter) {
                    $sheet->getStyle("{$columnLetter}{$totalRow}")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                    $sheet->getStyle("{$columnLetter}{$totalRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $sheet->getStyle("A{$totalRow}:I{$totalRow}")
                    ->getFont()
                    ->setBold(true);
            },
        ];
    }

    /**
     * @return array{
     *     contracts_count: int,
     *     contracts_total: float,
     *     total_paid: float,
     *     balance_total: float,
     *     surplus_total: float,
     *     suspended_total: float,
     *     wrong_total: float
     * }
     */
    protected function totals(): array
    {
        return [
            'contracts_count' => (int) $this->rows->sum('contracts_count'),
            'contracts_total' => (float) $this->rows->sum('contracts_total'),
            'total_paid' => (float) $this->rows->sum('total_paid'),
            'balance_total' => (float) $this->rows->sum('balance_total'),
            'surplus_total' => (float) $this->rows->sum('surplus_total'),
            'suspended_total' => (float) $this->rows->sum('suspended_total'),
            'wrong_total' => (float) $this->rows->sum('wrong_total'),
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function headerLayout(): array
    {
        if ($this->headerLayout !== null) {
            return $this->headerLayout;
        }

        $headings = [];
        $rightAlignedRows = [];
        $centeredInfoRows = [];
        $row = 1;

        if ($this->company) {
            foreach ($this->company->excelCompanyRows() as $companyRow) {
                $headings[] = $companyRow;
                $rightAlignedRows[] = $row++;
            }
        } else {
            $headings[] = [''];
            $rightAlignedRows[] = $row++;
        }

        $headings[] = [''];
        $row++;
        $headings[] = [''];
        $row++;

        $headings[] = [$this->reportTitle];
        $centeredInfoRows[] = $row++;

        $headings[] = [''];
        $row++;

        $nameHeading = $this->filterBy === 2 ? 'المصرف التجميعي' : 'الاسم';

        $headings[] = [
            'الرقم',
            $nameHeading,
            'عدد العقود',
            'اجمالي العقود',
            'المسدد',
            'الرصيد',
            'الفائض',
            'الترجيع',
            'بالخطأ',
        ];

        return $this->headerLayout = [
            'headings' => $headings,
            'headerRow' => $row,
            'rightAlignedRows' => $rightAlignedRows,
            'centeredInfoRows' => $centeredInfoRows,
        ];
    }
}
