<?php

namespace App\Exports\Installments;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\OurCompany;
use App\Models\WrongDeduction;
use App\Support\ErpNumber;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WrongDeductionsExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    use RtlInstallmentSheet;

    /**
     * @param  Collection<int, WrongDeduction>  $rows
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
     * @param  WrongDeduction  $row
     * @return array<int, string|null>
     */
    public function map($row): array
    {
        return [
            Utf8Text::clean($row->name),
            Utf8Text::clean($row->account_number),
            $row->deduction_date?->format('Y-m-d'),
            ErpNumber::money($row->amount),
        ];
    }

    public function headings(): array
    {
        $headings = [
            [Utf8Text::clean($this->company?->display_name) ?? ''],
            ['تقرير بالأقساط الواردة بالخطأ حتى تاريخ: '.$this->reportDate],
        ];

        foreach ($this->filterLines as $line) {
            $headings[] = [Utf8Text::clean($line)];
        }

        $headings[] = [''];
        $headings[] = ['اسم الزبون', 'رقم الحساب', 'التاريخ', 'القسط'];

        return $headings;
    }

    public function registerEvents(): array
    {
        return $this->rtlSheetEvents(
            headerRow: 4 + count($this->filterLines),
            columnCount: 4,
        );
    }
}
