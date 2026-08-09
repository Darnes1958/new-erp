<?php

namespace App\Exports\Finance;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\Expense;
use App\Models\OurCompany;
use App\Services\Finance\ExpenseListService;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ExpensesListExport implements FromCollection, WithEvents, WithHeadings, WithMapping
{
    use RtlInstallmentSheet;

    private const HEADER_ROW = 8;

    private const DATA_START_ROW = 9;

    protected ExpenseListService $service;

    /**
     * @param  Collection<int, Expense>  $rows
     * @param  array{
     *     dateFrom: ?string,
     *     dateTo: ?string,
     *     expenseTypeName: ?string,
     *     warehouseName: ?string
     * }  $meta
     */
    public function __construct(
        protected Collection $rows,
        protected ?OurCompany $company,
        protected array $meta,
    ) {
        $this->service = app(ExpenseListService::class);
    }

    public function collection(): Collection
    {
        return collect();
    }

    /** @param  Expense  $row */
    public function map($row): array
    {
        return $this->service->mapExcelRow($row);
    }

    public function headings(): array
    {
        return [
            [Utf8Text::clean($this->company?->CompanyName)],
            [Utf8Text::clean($this->company?->CompanyNameSuffix)],
            [''],
            ['مصروفات'],
            [$this->metaLine()],
            [''],
            [''],
            $this->service->headers(),
        ];
    }

    public function registerEvents(): array
    {
        $rtlEvents = $this->rtlSheetEvents(
            headerRow: self::HEADER_ROW,
            columnCount: 7,
            columnWidths: [
                'A' => 14,
                'B' => 24,
                'C' => 20,
                'D' => 20,
                'E' => 20,
                'F' => 14,
                'G' => 40,
            ],
            rightAlignedRows: [1, 2],
            centeredInfoRows: [4],
        );

        return [
            AfterSheet::class => function (AfterSheet $event) use ($rtlEvents): void {
                $rtlEvents[AfterSheet::class]($event);

                $sheet = $event->sheet->getDelegate();
                $rowIndex = self::DATA_START_ROW;

                foreach ($this->rows as $entry) {
                    $sheet->fromArray($this->map($entry), null, "A{$rowIndex}");
                    $rowIndex++;
                }

                if ($this->rows->isNotEmpty()) {
                    $sheet->fromArray($this->service->totalsRow($this->rows), null, "A{$rowIndex}");
                    $sheet->getStyle("A{$rowIndex}:G{$rowIndex}")->getFont()->setBold(true);
                }

                $dataEndRow = max(self::DATA_START_ROW, $rowIndex - 1);

                foreach (['F'] as $column) {
                    $sheet->getStyle("{$column}".self::DATA_START_ROW.":{$column}{$dataEndRow}")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                    $sheet->getStyle("{$column}".self::DATA_START_ROW.":{$column}{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }

    protected function metaLine(): string
    {
        $parts = [];

        if ($this->meta['dateFrom'] || $this->meta['dateTo']) {
            $parts[] = 'الفترة: '.($this->meta['dateFrom'] ?? '—').' — '.($this->meta['dateTo'] ?? '—');
        }

        if ($this->meta['expenseTypeName']) {
            $parts[] = 'نوع المصروفات: '.$this->meta['expenseTypeName'];
        }

        if ($this->meta['warehouseName']) {
            $parts[] = 'المكان: '.$this->meta['warehouseName'];
        }

        return Utf8Text::clean($parts !== [] ? implode('    ', $parts) : 'الكل');
    }
}
