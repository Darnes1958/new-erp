<?php

namespace App\Exports\Installments;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\OurCompany;
use App\Models\PayrollBank;
use App\Services\Installments\InstallmentBankCommissionReportService;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class BankCommissionExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    use RtlInstallmentSheet;

    /** @var array<string, mixed>|null */
    protected ?array $headerLayout = null;

    /**
     * @param  Collection<int, PayrollBank>  $rows
     */
    public function __construct(
        protected Collection $rows,
        protected ?OurCompany $company,
        protected string $reportTitle,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @param  PayrollBank  $row
     * @return array<int, string|float|null>
     */
    public function map($row): array
    {
        $service = app(InstallmentBankCommissionReportService::class);

        return [
            Utf8Text::clean($row->bankMain?->name ?? '—'),
            Utf8Text::clean($row->name),
            (int) ($row->installments_count ?? 0),
            (float) ($row->collected_total ?? 0),
            $service->commissionFor($row),
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
            columnCount: 5,
            columnWidths: [
                'A' => 26,
                'B' => 30,
                'C' => 16,
                'D' => 20,
                'E' => 18,
            ],
            rightAlignedRows: $layout['rightAlignedRows'],
            centeredInfoRows: $layout['centeredInfoRows'],
        );

        $rtlCallback = $rtlEvents[AfterSheet::class];
        $totals = app(InstallmentBankCommissionReportService::class)->summaryFromRows($this->rows);

        return [
            AfterSheet::class => function (AfterSheet $event) use ($rtlCallback, $layout, $totals): void {
                $rtlCallback($event);

                $sheet = $event->sheet->getDelegate();
                $headerRow = $layout['headerRow'];
                $dataEndRow = $sheet->getHighestRow();

                if ($dataEndRow > $headerRow) {
                    $sheet->getStyle('A'.($headerRow + 1).":B{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('C'.($headerRow + 1).":C{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('D'.($headerRow + 1).":E{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle('D'.($headerRow + 1).":E{$dataEndRow}")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                }

                if ($dataEndRow <= $headerRow) {
                    return;
                }

                $totalRow = $dataEndRow + 1;
                $sheet->setCellValue("A{$totalRow}", 'الإجمالي');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
                $sheet->setCellValue("C{$totalRow}", $totals['installments_count']);
                $sheet->setCellValue("D{$totalRow}", $totals['collected_total']);
                $sheet->setCellValue("E{$totalRow}", $totals['commission_total']);

                $sheet->getStyle("D{$totalRow}:E{$totalRow}")
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                $sheet->getStyle("C{$totalRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$totalRow}:E{$totalRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")
                    ->getFont()
                    ->setBold(true);
            },
        ];
    }

    /**
     * @return array{
     *     headings: array<int, array<int, string>>,
     *     headerRow: int,
     *     rightAlignedRows: array<int, int>,
     *     centeredInfoRows: array<int, int>
     * }
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

        $headings[] = [
            'المصرف الأم',
            'الحساب التجميعي',
            'عدد الأقساط المحصلة',
            'اجمالي الأقساط المحصلة',
            'العمولة',
        ];

        return $this->headerLayout = [
            'headings' => $headings,
            'headerRow' => $row,
            'rightAlignedRows' => $rightAlignedRows,
            'centeredInfoRows' => $centeredInfoRows,
        ];
    }
}
