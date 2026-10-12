<?php

namespace App\Exports\Market;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\OurCompany;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class CustomerBalancesExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    use RtlInstallmentSheet;

    private const HEADER_ROW = 8;

    private const DATA_START_ROW = 9;

    /** @var array<int, string> */
    protected array $customerTypeNames = [];

    /**
     * @param  Collection<int, object>  $rows
     * @param  array{debit: float, credit: float, balance: float}  $summary
     */
    public function __construct(
        protected Collection $rows,
        protected ?OurCompany $company,
        protected string $reportTitle,
        protected array $summary,
    ) {
        $typeNames = CustomerType::query()->pluck('name', 'id')->all();
        $customerTypes = Customer::query()->pluck('customer_type_id', 'id');

        foreach ($customerTypes as $customerId => $typeId) {
            $this->customerTypeNames[(int) $customerId] = $typeNames[$typeId] ?? '—';
        }
    }

    public function collection(): Collection
    {
        return collect();
    }

    public function map($row): array
    {
        return [
            Utf8Text::clean($this->customerTypeNames[(int) $row->customer_id] ?? '—'),
            Utf8Text::clean($row->name),
            (float) $row->mden,
            (float) $row->daen,
            (float) $row->raseed,
        ];
    }

    public function headings(): array
    {
        return [
            [Utf8Text::clean($this->company?->display_name)],
            [Utf8Text::clean($this->company?->display_name_suffix)],
            [''],
            [''],
            [''],
            [''],
            [''],
            ['التصنيف', 'الاسم', 'مدين', 'دائن', 'الرصيد'],
        ];
    }

    public function registerEvents(): array
    {
        $rtlEvents = $this->rtlSheetEvents(
            headerRow: self::HEADER_ROW,
            columnCount: 5,
            columnWidths: [
                'A' => 14,
                'B' => 50,
                'C' => 14,
                'D' => 14,
                'E' => 14,
            ],
            rightAlignedRows: [2],
            centeredInfoRows: [5],
        );

        return [
            AfterSheet::class => function (AfterSheet $event) use ($rtlEvents): void {
                $rtlEvents[AfterSheet::class]($event);

                $sheet = $event->sheet->getDelegate();

                $sheet->mergeCells('A1:C1');
                $sheet->setCellValue('A1', Utf8Text::clean($this->company?->display_name));
                $sheet->getStyle('A1')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

                $sheet->mergeCells('D1:E1');
                $sheet->setCellValue('D1', 'تاريخ الطباعة : '.now()->toDateString());
                $sheet->getStyle('D1:E1')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('D1')->getFont()->setSize(11);

                $sheet->getStyle('A2')->getFont()->setSize(13);

                $sheet->mergeCells('A5:E5');
                $sheet->setCellValue('A5', $this->reportTitle);

                $rowIndex = self::DATA_START_ROW;

                foreach ($this->rows as $entry) {
                    $sheet->fromArray($this->map($entry), null, "A{$rowIndex}");
                    $rowIndex++;
                }

                $dataEndRow = $rowIndex - 1;

                if ($dataEndRow >= self::DATA_START_ROW) {
                    $sheet->getStyle('A'.self::DATA_START_ROW.":A{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    foreach (['C', 'D', 'E'] as $column) {
                        $sheet->getStyle("{$column}".self::DATA_START_ROW.":{$column}{$dataEndRow}")
                            ->getNumberFormat()
                            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                        $sheet->getStyle("{$column}".self::DATA_START_ROW.":{$column}{$dataEndRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }

                $totalRow = max(self::DATA_START_ROW, $dataEndRow + 1);
                $sheet->setCellValue("B{$totalRow}", 'الإجمالـــــــــي');
                $sheet->setCellValue("C{$totalRow}", (float) $this->summary['debit']);
                $sheet->setCellValue("D{$totalRow}", (float) $this->summary['credit']);
                $sheet->setCellValue("E{$totalRow}", (float) $this->summary['balance']);

                $sheet->getStyle("A{$totalRow}:E{$totalRow}")
                    ->getFont()
                    ->setBold(true);
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FF9DC1D3');

                foreach (['C', 'D', 'E'] as $column) {
                    $sheet->getStyle("{$column}{$totalRow}")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                    $sheet->getStyle("{$column}{$totalRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }
}
