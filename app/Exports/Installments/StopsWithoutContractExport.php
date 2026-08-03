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

    protected int $sequence = 0;

    /**
     * @param  Collection<int, InstallmentStopWithoutContract>  $rows
     * @param  array<int, string>  $filterLines
     */
    public function __construct(
        protected Collection $rows,
        protected ?OurCompany $company,
        protected array $filterLines,
        protected string $reportDate,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @param  InstallmentStopWithoutContract  $row
     * @return array<int, string|int|null>
     */
    public function map($row): array
    {
        return [
            ++$this->sequence,
            $row->payrollBank?->name,
            $row->account_number,
            $row->name,
            $row->stop_date?->format('Y-m-d'),
        ];
    }

    public function headings(): array
    {
        $headings = [
            [$this->company?->display_name ?? ''],
            ['كشف إيقاف خصم بدون عقد حتى تاريخ: '.$this->reportDate],
        ];

        foreach ($this->filterLines as $line) {
            $headings[] = [Utf8Text::clean($line)];
        }

        $headings[] = [''];
        $headings[] = ['ت', 'المصرف التجميعي', 'رقم الحساب', 'الاسم', 'تاريخ الإيقاف'];

        return $headings;
    }

    public function registerEvents(): array
    {
        return $this->rtlSheetEvents(
            headerRow: 4 + count($this->filterLines),
            columnCount: 5,
        );
    }
}
