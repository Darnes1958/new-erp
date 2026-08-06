<?php

namespace App\Exports\Finance;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\Expense;
use App\Models\OurCompany;
use App\Models\RentTransaction;
use App\Models\SalaryTransaction;
use App\Services\Finance\FinanceMovementReportService;
use App\Support\ErpNumber;
use App\Support\Utf8Text;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FinanceMovementExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    use RtlInstallmentSheet;

    /** @var array<string, mixed>|null */
    protected ?array $headerLayout = null;

    /**
     * @param  Collection<int, Expense|SalaryTransaction|RentTransaction>  $rows
     */
    public function __construct(
        protected Collection $rows,
        protected ?OurCompany $company,
        protected string $reportTitle,
        protected string $kind,
        protected ?float $balance = null,
        protected ?string $subtitle = null,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @param  Expense|SalaryTransaction|RentTransaction  $row
     * @return array<int, string|null>
     */
    public function map($row): array
    {
        $service = app(FinanceMovementReportService::class);

        if ($this->kind === 'expense') {
            /** @var Expense $row */
            return [
                $row->expense_date?->format('Y-m-d'),
                Utf8Text::clean($service->expensePaymentSourceLabel($row)),
                ErpNumber::money($row->amount),
                Utf8Text::clean($row->notes),
            ];
        }

        /** @var SalaryTransaction|RentTransaction $row */
        return [
            $row->transaction_date?->format('Y-m-d'),
            Utf8Text::clean($row->transaction_type?->getLabel()),
            Utf8Text::clean($service->paymentSourceLabel($row)),
            $service->formatPeriodMonth($row->period_month),
            ErpNumber::money($row->amount),
            Utf8Text::clean($row->notes),
        ];
    }

    public function headings(): array
    {
        return $this->headerLayout()['headings'];
    }

    public function registerEvents(): array
    {
        $layout = $this->headerLayout();

        return $this->rtlSheetEvents(
            headerRow: $layout['headerRow'],
            columnCount: $layout['columnCount'],
            columnWidths: $layout['columnWidths'],
            totalAmount: (float) $this->rows->sum(fn (Model $row): float => (float) $row->amount),
            rightAlignedRows: $layout['rightAlignedRows'],
            centeredInfoRows: $layout['centeredInfoRows'],
        );
    }

    /**
     * @return array{
     *     headings: array<int, array<int, string>>,
     *     headerRow: int,
     *     columnCount: int,
     *     columnWidths: array<string, float|int>,
     *     rightAlignedRows: array<int, int>,
     *     centeredInfoRows: array<int, int>
     * }
     */
    protected function headerLayout(): array
    {
        if ($this->headerLayout !== null) {
            return $this->headerLayout;
        }

        $companyName = Utf8Text::clean($this->company?->CompanyName);
        $companySuffix = Utf8Text::clean($this->company?->CompanyNameSuffix);

        if ($this->kind === 'expense') {
            $centeredInfoRows = [4];
            $headings = [
                [$companyName],
                [$companySuffix],
                [''],
                [$this->reportTitle],
            ];

            if (filled($this->subtitle)) {
                $centeredInfoRows[] = 5;
                $headings[] = [$this->subtitle];
            }

            $headings[] = [''];
            $headings[] = [''];
            $headings[] = ['التاريخ', 'المصرف / الخزينة', 'المبلغ', 'ملاحظات'];
            $headerRow = count($headings);

            return $this->headerLayout = [
                'headings' => $headings,
                'headerRow' => $headerRow,
                'columnCount' => 4,
                'columnWidths' => [
                    'A' => 14,
                    'B' => 30,
                    'C' => 14,
                    'D' => 40,
                ],
                'rightAlignedRows' => [1, 2],
                'centeredInfoRows' => $centeredInfoRows,
            ];
        }

        $headings = [
            [$companyName],
            [$companySuffix],
            [''],
            [$this->reportTitle],
            [''],
            ['الرصيد : '.ErpNumber::money($this->balance ?? 0)],
            [''],
            ['التاريخ', 'البيان', 'دفعت من', 'عن شهر', 'المبلغ', 'ملاحظات'],
        ];

        return $this->headerLayout = [
            'headings' => $headings,
            'headerRow' => count($headings),
            'columnCount' => 6,
            'columnWidths' => [
                'A' => 14,
                'B' => 14,
                'C' => 30,
                'D' => 14,
                'E' => 14,
                'F' => 40,
            ],
            'rightAlignedRows' => [1, 2],
            'centeredInfoRows' => [4, 6],
        ];
    }
}
