<?php

namespace App\Exports\Installments;

use App\Enums\BankReportType;
use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\InstallmentContract;
use App\Models\InstallmentDeduction;
use App\Models\OurCompany;
use App\Support\ErpNumber;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class BankReportExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    use RtlInstallmentSheet;

    /** @var array<string, mixed>|null */
    protected ?array $headerLayout = null;

    /**
     * @param  Collection<int, InstallmentContract|InstallmentDeduction>  $rows
     * @param  array<int, string>  $filterLines
     */
    public function __construct(
        protected Collection $rows,
        protected BankReportType $type,
        protected ?OurCompany $company,
        protected array $filterLines,
        protected string $reportTitle,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @param  InstallmentContract|InstallmentDeduction  $row
     * @return array<int, string|int|null>
     */
    public function map($row): array
    {
        return match ($this->type) {
            BankReportType::Collected => $this->mapCollectedRow($row),
            BankReportType::Late => $this->mapLateRow($row),
            BankReportType::Uncollected => $this->mapUncollectedRow($row),
            default => $this->mapStandardContractRow($row),
        };
    }

    public function headings(): array
    {
        return $this->headerLayout()['headings'];
    }

    public function registerEvents(): array
    {
        $layout = $this->headerLayout();
        $columnCount = $this->columnCount();
        $rtlEvents = $this->rtlSheetEvents(
            headerRow: $layout['headerRow'],
            columnCount: $columnCount,
            columnWidths: $this->columnWidths(),
            rightAlignedRows: $layout['rightAlignedRows'],
            centeredInfoRows: $layout['centeredInfoRows'],
        );

        $rtlCallback = $rtlEvents[AfterSheet::class];
        $totals = $this->totalsByColumn();

        return [
            AfterSheet::class => function (AfterSheet $event) use ($rtlCallback, $layout, $totals): void {
                $rtlCallback($event);

                $sheet = $event->sheet->getDelegate();
                $headerRow = $layout['headerRow'];
                $dataEndRow = $sheet->getHighestRow();

                if ($dataEndRow > $headerRow) {
                    $lastColumn = chr(ord('A') + $this->columnCount() - 1);
                    $dataRange = 'A'.($headerRow + 1).":{$lastColumn}{$dataEndRow}";

                    $sheet->getStyle($dataRange)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    foreach ($this->centeredDataColumns() as $columnLetter) {
                        $sheet->getStyle("{$columnLetter}".($headerRow + 1).":{$columnLetter}{$dataEndRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }

                if ($totals === []) {
                    return;
                }

                if ($dataEndRow <= $headerRow) {
                    return;
                }

                $totalRow = $dataEndRow + 1;
                $sheet->setCellValue("A{$totalRow}", 'الإجمالي');

                foreach ($totals as $columnLetter => $value) {
                    $sheet->setCellValue("{$columnLetter}{$totalRow}", $value);
                    $sheet->getStyle("{$columnLetter}{$totalRow}")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                    $sheet->getStyle("{$columnLetter}{$totalRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $sheet->getStyle("A{$totalRow}:{$sheet->getHighestColumn()}{$totalRow}")
                    ->getFont()
                    ->setBold(true);
            },
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function centeredDataColumns(): array
    {
        return match ($this->type) {
            BankReportType::Collected => ['B', 'F'],
            BankReportType::Late => ['B', 'H'],
            BankReportType::Uncollected => ['B', 'G'],
            default => ['B'],
        };
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

        foreach ($this->filterLines as $line) {
            $headings[] = [Utf8Text::clean($line)];
            $rightAlignedRows[] = $row++;
        }

        $headings[] = [''];
        $row++;

        $headings[] = $this->tableHeadings();

        return $this->headerLayout = [
            'headings' => $headings,
            'headerRow' => $row,
            'rightAlignedRows' => $rightAlignedRows,
            'centeredInfoRows' => $centeredInfoRows,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function tableHeadings(): array
    {
        return match ($this->type) {
            BankReportType::Collected => [
                'اسم الزبون',
                'رقم العقد',
                'اجمالي العقد',
                'القسط',
                'المسدد',
                'تاريخ الخصم',
                'الخصم',
            ],
            BankReportType::Late => [
                'اسم الزبون',
                'رقم العقد',
                'رقم الحساب',
                'اجمالي العقد',
                'القسط',
                'المسدد',
                'المتأخرة',
                'ت.آخر قسط',
            ],
            BankReportType::Uncollected => [
                'اسم الزبون',
                'رقم العقد',
                'اجمالي العقد',
                'القسط',
                'المسدد',
                'الرصيد',
                'تاريخ آخر خصم',
            ],
            default => [
                'اسم الزبون',
                'رقم العقد',
                'رقم الحساب',
                'اجمالي العقد',
                'القسط',
                'المسدد',
                'الرصيد',
            ],
        };
    }

    protected function columnCount(): int
    {
        return count($this->tableHeadings());
    }

    /**
     * @return array<string, float|int>
     */
    protected function columnWidths(): array
    {
        return match ($this->type) {
            BankReportType::Late => [
                'A' => 36,
                'B' => 12,
                'C' => 20,
                'D' => 14,
                'E' => 14,
                'F' => 14,
                'G' => 12,
                'H' => 14,
            ],
            BankReportType::Collected, BankReportType::Uncollected => [
                'A' => 36,
                'B' => 12,
                'C' => 14,
                'D' => 14,
                'E' => 14,
                'F' => 14,
                'G' => 14,
            ],
            default => [
                'A' => 36,
                'B' => 12,
                'C' => 20,
                'D' => 14,
                'E' => 14,
                'F' => 14,
                'G' => 14,
            ],
        };
    }

    /**
     * @return array<string, float|int>
     */
    protected function totalsByColumn(): array
    {
        return match ($this->type) {
            BankReportType::Collected => [
                'C' => (float) $this->rows->sum(fn (InstallmentDeduction $row): float => (float) ($row->installmentContract?->contract_total ?? 0)),
                'E' => (float) $this->rows->sum(fn (InstallmentDeduction $row): float => (float) ($row->installmentContract?->total_paid ?? 0)),
                'G' => (float) $this->rows->sum(fn (InstallmentDeduction $row): float => (float) $row->deducted_amount),
            ],
            BankReportType::Late => [
                'D' => (float) $this->rows->sum(fn (InstallmentContract $row): float => (float) $row->contract_total),
                'F' => (float) $this->rows->sum(fn (InstallmentContract $row): float => (float) $row->total_paid),
            ],
            BankReportType::Uncollected => [
                'C' => (float) $this->rows->sum(fn (InstallmentContract $row): float => (float) $row->contract_total),
                'E' => (float) $this->rows->sum(fn (InstallmentContract $row): float => (float) $row->total_paid),
                'F' => (float) $this->rows->sum(fn (InstallmentContract $row): float => (float) $row->balance),
            ],
            default => [
                'D' => (float) $this->rows->sum(fn (InstallmentContract $row): float => (float) $row->contract_total),
                'F' => (float) $this->rows->sum(fn (InstallmentContract $row): float => (float) $row->total_paid),
                'G' => (float) $this->rows->sum(fn (InstallmentContract $row): float => (float) $row->balance),
            ],
        };
    }

    /**
     * @return array<int, string|int|null>
     */
    protected function mapStandardContractRow(InstallmentContract $row): array
    {
        return [
            Utf8Text::clean($row->customer?->name),
            (string) $row->id,
            Utf8Text::clean($row->bank_account_number),
            ErpNumber::money($row->contract_total),
            ErpNumber::money($row->installment_amount),
            ErpNumber::money($row->total_paid),
            ErpNumber::money($row->balance),
        ];
    }

    /**
     * @return array<int, string|int|null>
     */
    protected function mapLateRow(InstallmentContract $row): array
    {
        return [
            Utf8Text::clean($row->customer?->name),
            (string) $row->id,
            Utf8Text::clean($row->bank_account_number),
            ErpNumber::money($row->contract_total),
            ErpNumber::money($row->installment_amount),
            ErpNumber::money($row->total_paid),
            (string) (int) $row->late_amount,
            $row->last_deduction_month?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<int, string|int|null>
     */
    protected function mapUncollectedRow(InstallmentContract $row): array
    {
        return [
            Utf8Text::clean($row->customer?->name),
            (string) $row->id,
            ErpNumber::money($row->contract_total),
            ErpNumber::money($row->installment_amount),
            ErpNumber::money($row->total_paid),
            ErpNumber::money($row->balance),
            $row->last_deduction_month?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<int, string|int|null>
     */
    protected function mapCollectedRow(InstallmentDeduction $row): array
    {
        $contract = $row->installmentContract;

        return [
            Utf8Text::clean($contract?->customer?->name),
            (string) $row->installment_contract_id,
            ErpNumber::money($contract?->contract_total),
            ErpNumber::money($contract?->installment_amount),
            ErpNumber::money($contract?->total_paid),
            $row->deduction_date?->format('Y-m-d'),
            ErpNumber::money($row->deducted_amount),
        ];
    }
}
