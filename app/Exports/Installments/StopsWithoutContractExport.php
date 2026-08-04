<?php

namespace App\Exports\Installments;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\InstallmentStopWithoutContract;
use App\Models\OurCompany;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StopsWithoutContractExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    use RtlInstallmentSheet;

    /** @var array<string, mixed>|null */
    protected ?array $headerLayout = null;

    /**
     * @param  Collection<int, InstallmentStopWithoutContract>  $rows
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
     * @param  InstallmentStopWithoutContract  $row
     * @return array<int, string|null>
     */
    public function map($row): array
    {
        return [
            Utf8Text::clean($row->name),
            Utf8Text::clean($row->account_number),
            Utf8Text::clean($row->payrollBank?->name),
            $row->stop_date?->format('Y-m-d'),
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
            columnCount: 4,
            columnWidths: [
                'A' => 32,
                'B' => 20,
                'C' => 40,
                'D' => 14,
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

        $headings[] = ['اسم الزبون', 'رقم الحساب', 'المصرف التجميعي', 'تاريخ الإيقاف'];

        return $this->headerLayout = [
            'headings' => $headings,
            'headerRow' => $row,
            'rightAlignedRows' => $rightAlignedRows,
            'centeredInfoRows' => $centeredInfoRows,
        ];
    }
}
