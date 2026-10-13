<?php

namespace App\Exports\Market;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Exports\Market\Concerns\ColorsSignedExcelBalances;
use App\Models\OurCompany;
use App\Models\SupplierLedgerEntry;
use App\Services\Market\SupplierLedgerReportService;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SupplierMovementExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    use ColorsSignedExcelBalances;
    use RtlInstallmentSheet;

    private const HEADER_ROW = 8;

    private const DATA_START_ROW = 9;

    protected SupplierLedgerReportService $service;

    /**
     * @param  Collection<int, SupplierLedgerEntry>  $rows
     */
    public function __construct(
        protected Collection $rows,
        protected ?OurCompany $company,
        protected string $supplierName,
        protected string $dateFrom,
        protected float $openingBalance,
        protected array $periodTotals,
    ) {
        $this->service = app(SupplierLedgerReportService::class);
    }

    public function collection(): Collection
    {
        return collect();
    }

    /** @param  SupplierLedgerEntry  $row */
    public function map($row): array
    {
        return [
            $row->rep_date?->format('Y-m-d'),
            (string) $row->id,
            Utf8Text::clean($this->service->transactionKindLabel((int) $row->transaction_kind)),
            (float) $row->mden,
            (float) $row->daen,
            (float) ($row->running_balance ?? 0),
            Utf8Text::clean($row->notes),
        ];
    }

    public function headings(): array
    {
        return [
            [Utf8Text::clean($this->company?->CompanyName)],
            [Utf8Text::clean($this->company?->CompanyNameSuffix)],
            [''],
            [''],
            [''],
            [''],
            [''],
            ['التاريخ', 'الرقم الألي', 'البيان', 'مدين', 'دائن', 'الرصيد', 'ملاحظات'],
        ];
    }

    public function registerEvents(): array
    {
        $rtlEvents = $this->rtlSheetEvents(
            headerRow: self::HEADER_ROW,
            columnCount: 7,
            columnWidths: [
                'A' => 14,
                'B' => 12,
                'C' => 18,
                'D' => 14,
                'E' => 14,
                'F' => 14,
                'G' => 40,
            ],
            rightAlignedRows: [1, 2],
        );

        return [
            AfterSheet::class => function (AfterSheet $event) use ($rtlEvents): void {
                $rtlEvents[AfterSheet::class]($event);

                $sheet = $event->sheet->getDelegate();

                $infoRows = [
                    4 => 'كشف حساب المورد : '.Utf8Text::clean($this->supplierName),
                    5 => 'من تاريخ : '.$this->dateFrom,
                ];

                foreach ($infoRows as $row => $text) {
                    $sheet->mergeCells("A{$row}:G{$row}");
                    $sheet->setCellValue("A{$row}", $text);
                    $sheet->getStyle("A{$row}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("A{$row}")
                        ->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    if ($row === 4) {
                        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                    }
                }

                $sheet->mergeCells('A6:G6');
                $sheet->getCell('A6')->setValue($this->signedSummaryRichText([
                    ['label' => 'الرصيد السابق : ', 'value' => $this->openingBalance],
                ]));
                $sheet->getStyle('A6')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->mergeCells('A7:G7');
                $sheet->getCell('A7')->setValue($this->signedSummaryRichText([
                    ['label' => 'مدين : ', 'value' => (float) $this->periodTotals['debit'], 'signed' => false],
                    ['label' => 'دائن : ', 'value' => (float) $this->periodTotals['credit'], 'signed' => false],
                    ['label' => 'الرصيد : ', 'value' => (float) $this->periodTotals['balance']],
                ]));
                $sheet->getStyle('A7')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $rowIndex = self::DATA_START_ROW;

                foreach ($this->rows as $entry) {
                    $sheet->fromArray($this->map($entry), null, "A{$rowIndex}");
                    $rowIndex++;
                }

                $dataEndRow = $rowIndex - 1;

                if ($dataEndRow >= self::DATA_START_ROW) {
                    foreach (['B', 'C'] as $column) {
                        $sheet->getStyle("{$column}".self::DATA_START_ROW.":{$column}{$dataEndRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }

                    foreach (['D', 'E', 'F'] as $column) {
                        $sheet->getStyle("{$column}".self::DATA_START_ROW.":{$column}{$dataEndRow}")
                            ->getNumberFormat()
                            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                        $sheet->getStyle("{$column}".self::DATA_START_ROW.":{$column}{$dataEndRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }

                    $this->colorRunningBalanceColumn($sheet, $this->rows, 'F', self::DATA_START_ROW);
                }
            },
        ];
    }
}
