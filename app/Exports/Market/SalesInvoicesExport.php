<?php

namespace App\Exports\Market;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\OurCompany;
use App\Models\SalesInvoice;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SalesInvoicesExport implements FromCollection, WithEvents, WithHeadings, WithMapping
{
    use RtlInstallmentSheet;

    private const HEADER_ROW = 8;

    private const DATA_START_ROW = 9;

    /**
     * @param  Collection<int, SalesInvoice>  $rows
     * @param  array{
     *     dateFrom: ?string,
     *     dateTo: ?string,
     *     customerName: ?string,
     *     warehouseName: ?string,
     *     tabLabel: ?string,
     *     showProfit: bool
     * }  $meta
     */
    public function __construct(
        protected Collection $rows,
        protected ?OurCompany $company,
        protected array $meta,
    ) {}

    public function collection(): Collection
    {
        return collect();
    }

    /** @param  SalesInvoice  $row */
    public function map($row): array
    {
        $mapped = [
            (string) $row->id,
            $row->invoice_date?->format('Y-m-d'),
            Utf8Text::clean($row->customer?->name),
            Utf8Text::clean($row->paymentMethod?->name),
            (float) $row->lines_subtotal,
            (float) $row->extra_cost,
            (float) $row->discount,
            (float) $row->difference_amount,
            (float) $row->grand_total,
            (float) $row->amount_paid,
            (float) $row->balance,
        ];

        if ($this->meta['showProfit']) {
            $mapped[] = (float) ($row->profit_total ?? 0);
        }

        $mapped[] = Utf8Text::clean($row->notes);

        return $mapped;
    }

    public function headings(): array
    {
        $headers = ['الرقم', 'التاريخ', 'الزبون', 'طريقة الدفع', 'إجمالي البنود', 'تكاليف إضافية', 'خصم', 'فرق عملة', 'الإجمالي', 'المدفوع', 'الباقي'];

        if ($this->meta['showProfit']) {
            $headers[] = 'الربح';
        }

        $headers[] = 'ملاحظات';

        return [
            [Utf8Text::clean($this->company?->CompanyName)],
            [Utf8Text::clean($this->company?->CompanyNameSuffix)],
            [''],
            ['تقرير فواتير المبيعات'],
            [$this->metaLine()],
            [''],
            [''],
            $headers,
        ];
    }

    public function registerEvents(): array
    {
        $columnCount = $this->meta['showProfit'] ? 13 : 12;

        $rtlEvents = $this->rtlSheetEvents(
            headerRow: self::HEADER_ROW,
            columnCount: $columnCount,
            columnWidths: [
                'A' => 10,
                'B' => 14,
                'C' => 24,
                'D' => 14,
                'E' => 14,
                'F' => 14,
                'G' => 12,
                'H' => 12,
                'I' => 14,
                'J' => 14,
                'K' => 14,
                'L' => 14,
                'M' => 30,
            ],
            rightAlignedRows: [1, 2],
            centeredInfoRows: [4, 5],
        );

        return [
            AfterSheet::class => function (AfterSheet $event) use ($rtlEvents, $columnCount): void {
                $rtlEvents[AfterSheet::class]($event);

                $sheet = $event->sheet->getDelegate();
                $rowIndex = self::DATA_START_ROW;

                foreach ($this->rows as $invoice) {
                    $sheet->fromArray($this->map($invoice), null, "A{$rowIndex}");
                    $rowIndex++;
                }

                $dataEndRow = max(self::DATA_START_ROW, $rowIndex - 1);
                $moneyColumns = ['E', 'F', 'G', 'H', 'I', 'J', 'K'];

                if ($this->meta['showProfit']) {
                    $moneyColumns[] = 'L';
                }

                foreach ($moneyColumns as $column) {
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

        if ($this->meta['customerName']) {
            $parts[] = 'الزبون: '.$this->meta['customerName'];
        }

        if ($this->meta['warehouseName']) {
            $parts[] = 'المخزن: '.$this->meta['warehouseName'];
        }

        if (($this->meta['tabLabel'] ?? null) && $this->meta['tabLabel'] !== 'الكل') {
            $parts[] = 'النوع: '.$this->meta['tabLabel'];
        }

        return implode('    ', $parts) ?: '—';
    }
}
