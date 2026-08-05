<?php

namespace App\Exports\Installments;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\OurCompany;
use App\Models\PayrollBank;
use App\Services\Installments\InstallmentBranchCommissionReportService;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class BranchCommissionExport implements FromCollection, WithHeadings, WithMapping, WithEvents
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
        $service = app(InstallmentBranchCommissionReportService::class);

        return [
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
            columnCount: 4,
            columnWidths: [
                'A' => 34,
                'B' => 18,
                'C' => 22,
                'D' => 18,
            ],
            rightAlignedRows: $layout['rightAlignedRows'],
            centeredInfoRows: $layout['centeredInfoRows'],
        );

        $rtlCallback = $rtlEvents[AfterSheet::class];
        $totals = app(InstallmentBranchCommissionReportService::class)->summaryFromRows($this->rows);

        return [
            AfterSheet::class => function (AfterSheet $event) use ($rtlCallback, $layout, $totals): void {
                $rtlCallback($event);

                $sheet = $event->sheet->getDelegate();
                $headerRow = $layout['headerRow'];
                $dataEndRow = $sheet->getHighestRow();

                if ($dataEndRow > $headerRow) {
                    $sheet->getStyle('A'.($headerRow + 1).":A{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('B'.($headerRow + 1).":B{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('C'.($headerRow + 1).":D{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle('C'.($headerRow + 1).":D{$dataEndRow}")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                }

                if ($dataEndRow <= $headerRow) {
                    return;
                }

                $totalRow = $dataEndRow + 1;
                $sheet->setCellValue("A{$totalRow}", 'الإجمالي');
                $sheet->setCellValue("B{$totalRow}", $totals['installments_count']);
                $sheet->setCellValue("C{$totalRow}", $totals['collected_total']);
                $sheet->setCellValue("D{$totalRow}", $totals['commission_total']);

                $sheet->getStyle("C{$totalRow}:D{$totalRow}")
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                $sheet->getStyle("B{$totalRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$totalRow}:D{$totalRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A{$totalRow}:D{$totalRow}")
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
            'الحساب التجميعي',
            'عدد الأقساط المحصلة',
            'اجمالي الأقساط المحصلة',
            'عمولة المصرف',
        ];

        return $this->headerLayout = [
            'headings' => $headings,
            'headerRow' => $row,
            'rightAlignedRows' => $rightAlignedRows,
            'centeredInfoRows' => $centeredInfoRows,
        ];
    }
}
