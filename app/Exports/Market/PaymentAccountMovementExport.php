<?php

namespace App\Exports\Market;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\OurCompany;
use App\Services\Market\PaymentAccountLedgerReportService;
use App\Support\ErpNumber;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PaymentAccountMovementExport implements FromCollection, WithEvents, WithHeadings, WithMapping
{
    use RtlInstallmentSheet;

    private const HEADER_ROW = 8;

    private const DATA_START_ROW = 9;

    protected PaymentAccountLedgerReportService $service;

    /**
     * @param  Collection<int, object>  $rows
     * @param  array{debit: float, credit: float, balance: float}  $periodTotals
     */
    public function __construct(
        protected Collection $rows,
        protected ?OurCompany $company,
        protected string $reportTitle,
        protected string $accountName,
        protected ?string $dateFrom,
        protected ?string $dateTo,
        protected float $openingBalance,
        protected array $periodTotals,
    ) {
        $this->service = app(PaymentAccountLedgerReportService::class);
    }

    public function collection(): Collection
    {
        return collect();
    }

    public function map($row): array
    {
        return [
            Utf8Text::clean($this->service->transactionKindLabel((int) $row->transaction_kind)),
            Utf8Text::clean($row->party_name),
            $row->rep_date?->format('Y-m-d'),
            (float) $row->mden,
            (float) $row->daen,
            (float) ($row->running_balance ?? 0),
            (string) ($row->document_no ?? ''),
            Utf8Text::clean($row->notes),
        ];
    }

    public function headings(): array
    {
        $periodLine = 'من تاريخ: '.($this->dateFrom ?? '—');

        if ($this->dateTo) {
            $periodLine .= '    إلى تاريخ: '.$this->dateTo;
        }

        return [
            [Utf8Text::clean($this->company?->CompanyName)],
            [Utf8Text::clean($this->company?->CompanyNameSuffix)],
            [''],
            [$this->reportTitle.' : '.Utf8Text::clean($this->accountName)],
            [$periodLine],
            ['الرصيد السابق : '.ErpNumber::money($this->openingBalance)],
            ['مدين : '.ErpNumber::money($this->periodTotals['debit'])
                .'    دائن : '.ErpNumber::money($this->periodTotals['credit'])
                .'    الرصيد : '.ErpNumber::money($this->periodTotals['balance'])],
            ['البيان', 'الطرف', 'التاريخ', 'مدين', 'دائن', 'الرصيد', 'رقم المستند', 'ملاحظات'],
        ];
    }

    public function registerEvents(): array
    {
        $rtlEvents = $this->rtlSheetEvents(
            headerRow: self::HEADER_ROW,
            columnCount: 8,
            columnWidths: [
                'A' => 16,
                'B' => 22,
                'C' => 14,
                'D' => 14,
                'E' => 14,
                'F' => 14,
                'G' => 12,
                'H' => 30,
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
}
