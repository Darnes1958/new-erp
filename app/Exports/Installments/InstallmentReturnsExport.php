<?php

namespace App\Exports\Installments;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Filament\Ins\Resources\InstallmentReturns\Tables\InstallmentReturnsTable;
use App\Models\InstallmentSuspended;
use App\Models\OurCompany;
use App\Support\ErpNumber;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InstallmentReturnsExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    use RtlInstallmentSheet;

    /** @var array<string, mixed>|null */
    protected ?array $headerLayout = null;

    /**
     * @param  Collection<int, InstallmentSuspended>  $rows
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
     * @param  InstallmentSuspended  $row
     * @return array<int, string|null>
     */
    public function map($row): array
    {
        $contractId = $row->displayContractId();

        return [
            Utf8Text::clean(InstallmentReturnsTable::customerLabel($row)),
            $contractId !== null ? (string) $contractId : null,
            $row->suspended_date?->format('Y-m-d'),
            ErpNumber::money($row->amount),
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
                'A' => 36,
                'B' => 20,
                'C' => 14,
                'D' => 14,
            ],
            totalAmount: (float) $this->rows->sum(fn (InstallmentSuspended $row): float => (float) $row->amount),
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

        $headings[] = ['اسم الزبون', 'رقم العقد', 'التاريخ', 'المبلغ'];

        return $this->headerLayout = [
            'headings' => $headings,
            'headerRow' => $row,
            'rightAlignedRows' => $rightAlignedRows,
            'centeredInfoRows' => $centeredInfoRows,
        ];
    }
}
