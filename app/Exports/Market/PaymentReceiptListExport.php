<?php

namespace App\Exports\Market;

use App\Enums\ReceiptListKind;
use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\OurCompany;
use App\Services\Market\PaymentReceiptListService;
use App\Support\Utf8Text;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PaymentReceiptListExport implements FromCollection, WithEvents, WithHeadings, WithMapping
{
    use RtlInstallmentSheet;

    private const HEADER_ROW = 8;

    private const DATA_START_ROW = 9;

    protected PaymentReceiptListService $service;

    /**
     * @param  Collection<int, Model>  $rows
     * @param  array{
     *     dateFrom: ?string,
     *     dateTo: ?string,
     *     partyName: ?string,
     *     warehouseName: ?string,
     *     kindFilterLabel: ?string
     * }  $meta
     */
    public function __construct(
        protected Collection $rows,
        protected ?OurCompany $company,
        protected ReceiptListKind $kind,
        protected array $meta,
    ) {
        $this->service = app(PaymentReceiptListService::class);
    }

    public function collection(): Collection
    {
        return collect();
    }

    /** @param  Model  $row */
    public function map($row): array
    {
        return $this->service->mapExcelRow($row, $this->kind);
    }

    public function headings(): array
    {
        return [
            [Utf8Text::clean($this->company?->CompanyName)],
            [Utf8Text::clean($this->company?->CompanyNameSuffix)],
            [''],
            [$this->kind->reportTitle()],
            [$this->metaLine()],
            [''],
            [''],
            $this->service->headers($this->kind),
        ];
    }

    public function registerEvents(): array
    {
        $rtlEvents = $this->rtlSheetEvents(
            headerRow: self::HEADER_ROW,
            columnCount: 9,
            columnWidths: [
                'A' => 10,
                'B' => 14,
                'C' => 24,
                'D' => 14,
                'E' => 22,
                'F' => 16,
                'G' => 18,
                'H' => 14,
                'I' => 30,
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
                    $totals = $this->service->totalsRow($this->rows);
                    $totals[7] = round((float) $this->rows->sum(fn (Model $row): float => (float) $row->amount), 3);
                    $sheet->fromArray($totals, null, "A{$rowIndex}");
                    $sheet->getStyle("A{$rowIndex}:I{$rowIndex}")->getFont()->setBold(true);
                    $sheet->getStyle("H{$rowIndex}")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                    $sheet->getStyle("H{$rowIndex}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $dataEndRow = max(self::DATA_START_ROW, $rowIndex - 1);

                foreach (['H'] as $column) {
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

        if ($this->meta['partyName']) {
            $parts[] = $this->kind->partyLabel().': '.$this->meta['partyName'];
        }

        if ($this->meta['warehouseName']) {
            $parts[] = 'المخزن: '.$this->meta['warehouseName'];
        }

        if ($this->meta['kindFilterLabel']) {
            $parts[] = 'النوع: '.$this->meta['kindFilterLabel'];
        }

        return Utf8Text::clean($parts !== [] ? implode('    ', $parts) : 'الكل');
    }
}
