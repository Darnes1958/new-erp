<?php

namespace App\Exports\Installments;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\InstallmentContract;
use App\Models\OurCompany;
use App\Support\ErpNumber;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InstallmentStopExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    use RtlInstallmentSheet;

    /** @var array<string, mixed>|null */
    protected ?array $headerLayout = null;

    protected int $rowNumber = 0;

    /**
     * @param  Collection<int, InstallmentContract>  $rows
     * @param  array<int, string>  $filterLines
     */
    public function __construct(
        protected Collection $rows,
        protected ?OurCompany $company,
        protected array $filterLines,
        protected string $reportTitle,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @param  InstallmentContract  $row
     * @return array<int, string|int|null>
     */
    public function map($row): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            (string) $row->id,
            Utf8Text::clean($row->bank_account_number),
            Utf8Text::clean($row->customer?->name),
            ErpNumber::money($row->installment_amount),
            $row->stop?->stop_date?->format('Y-m-d'),
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
            columnCount: 6,
            columnWidths: [
                'A' => 6,
                'B' => 12,
                'C' => 20,
                'D' => 36,
                'E' => 14,
                'F' => 14,
            ],
            rightAlignedRows: $layout['rightAlignedRows'],
            centeredInfoRows: $layout['centeredInfoRows'],
        );
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

        foreach ($this->filterLines as $line) {
            $headings[] = [Utf8Text::clean($line)];
            $rightAlignedRows[] = $row++;
        }

        $headings[] = [''];
        $row++;

        $headings[] = [
            'ت',
            'رقم العقد',
            'رقم الحساب',
            'الاسم',
            'القسط',
            'التاريخ',
        ];

        return $this->headerLayout = [
            'headings' => $headings,
            'headerRow' => $row,
            'rightAlignedRows' => $rightAlignedRows,
            'centeredInfoRows' => $centeredInfoRows,
        ];
    }
}
