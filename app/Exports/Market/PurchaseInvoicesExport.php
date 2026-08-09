<?php

namespace App\Exports\Market;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\OurCompany;
use App\Models\PurchaseInvoice;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PurchaseInvoicesExport implements FromCollection, WithEvents, WithHeadings, WithMapping
{
    use RtlInstallmentSheet;

    private const HEADER_ROW = 8;

    private const DATA_START_ROW = 9;

    /**
     * @param  Collection<int, PurchaseInvoice>  $rows
     * @param  array{
     *     dateFrom: ?string,
     *     dateTo: ?string,
     *     supplierName: ?string,
     *     warehouseName: ?string
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

    /** @param  PurchaseInvoice  $row */
    public function map($row): array
    {
        return [
            (string) $row->id,
            $row->invoice_date?->format('Y-m-d'),
            Utf8Text::clean($row->supplier?->name),
            (float) $row->lines_subtotal - (float) $row->discount,
            (float) $row->amount_paid,
            (float) $row->balance,
            Utf8Text::clean($row->notes),
        ];
    }

    public function headings(): array
    {
        return [
            [Utf8Text::clean($this->company?->CompanyName)],
            [Utf8Text::clean($this->company?->CompanyNameSuffix)],
            [''],
            ['تقرير فواتير المشتريات'],
            [$this->metaLine()],
            [''],
            [''],
            ['الرقم', 'التاريخ', 'المورد', 'الإجمالي', 'المدفوع', 'الباقي', 'ملاحظات'],
        ];
    }

    public function registerEvents(): array
    {
        $rtlEvents = $this->rtlSheetEvents(
            headerRow: self::HEADER_ROW,
            columnCount: 7,
            columnWidths: [
                'A' => 10,
                'B' => 14,
                'C' => 24,
                'D' => 14,
                'E' => 14,
                'F' => 14,
                'G' => 30,
            ],
            rightAlignedRows: [1, 2],
            centeredInfoRows: [4, 5],
        );

        return [
            AfterSheet::class => function (AfterSheet $event) use ($rtlEvents): void {
                $rtlEvents[AfterSheet::class]($event);

                $sheet = $event->sheet->getDelegate();
                $rowIndex = self::DATA_START_ROW;

                foreach ($this->rows as $invoice) {
                    $sheet->fromArray($this->map($invoice), null, "A{$rowIndex}");
                    $rowIndex++;
                }

                $dataEndRow = max(self::DATA_START_ROW, $rowIndex - 1);

                foreach (['D', 'E', 'F'] as $column) {
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

        if ($this->meta['supplierName']) {
            $parts[] = 'المورد: '.$this->meta['supplierName'];
        }

        if ($this->meta['warehouseName']) {
            $parts[] = 'المخزن: '.$this->meta['warehouseName'];
        }

        return implode('    ', $parts) ?: '—';
    }
}
