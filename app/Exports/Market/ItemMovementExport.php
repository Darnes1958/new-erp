<?php

namespace App\Exports\Market;

use App\Exports\Installments\Concerns\RtlInstallmentSheet;
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

class ItemMovementExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    use RtlInstallmentSheet;

    private const HEADER_ROW = 8;

    private const DATA_START_ROW = 9;

    /**
     * @param  Collection<int, object>  $rows
     */
    public function __construct(
        protected Collection $rows,
        protected ?OurCompany $company,
        protected string $itemName,
        protected string $dateFrom,
        protected ?string $warehouseName,
    ) {}

    public function collection(): Collection
    {
        return collect();
    }

    public function map($row): array
    {
        return [
            $row->created_at,
            Utf8Text::clean($row->type),
            $row->order_date,
            (int) $row->id,
            Utf8Text::clean($row->name),
            Utf8Text::clean($row->price_type),
            Utf8Text::clean($row->place_name),
            Utf8Text::clean($row->notes),
            (float) $row->q1,
            (float) $row->price1,
            (float) $row->sub_tot,
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
            [
                'تاريخ الإدخال',
                'البيان',
                'تاريخ الفاتورة',
                'رقم الفاتورة',
                'العميل',
                'طريقة الدفع',
                'المكان',
                'ملاحظات',
                'الكمية',
                'السعر',
                'المجموع',
            ],
        ];
    }

    public function registerEvents(): array
    {
        $rtlEvents = $this->rtlSheetEvents(
            headerRow: self::HEADER_ROW,
            columnCount: 11,
            columnWidths: [
                'A' => 20,
                'B' => 14,
                'C' => 14,
                'D' => 14,
                'E' => 40,
                'F' => 14,
                'G' => 20,
                'H' => 40,
                'I' => 14,
                'J' => 14,
                'K' => 18,
            ],
            rightAlignedRows: [1, 2],
        );

        return [
            AfterSheet::class => function (AfterSheet $event) use ($rtlEvents): void {
                $rtlEvents[AfterSheet::class]($event);

                $sheet = $event->sheet->getDelegate();

                $sheet->setCellValue(
                    'D6',
                    Utf8Text::clean('حركة الصنف: '.$this->itemName.'      من تاريخ '.$this->dateFrom),
                );

                if ($this->warehouseName) {
                    $sheet->setCellValue('H6', Utf8Text::clean('المكان: '.$this->warehouseName));
                }

                $sheet->getStyle('D6:H6')->getFont()->setBold(true);

                $rowIndex = self::DATA_START_ROW;

                foreach ($this->rows as $row) {
                    $sheet->setCellValue('A'.$rowIndex, $row->created_at);
                    $sheet->setCellValue('B'.$rowIndex, Utf8Text::clean($row->type));
                    $sheet->setCellValue('C'.$rowIndex, $row->order_date);
                    $sheet->setCellValue('D'.$rowIndex, (int) $row->id);
                    $sheet->setCellValue('E'.$rowIndex, Utf8Text::clean($row->name));
                    $sheet->setCellValue('F'.$rowIndex, Utf8Text::clean($row->price_type));
                    $sheet->setCellValue('G'.$rowIndex, Utf8Text::clean($row->place_name));
                    $sheet->setCellValue('H'.$rowIndex, Utf8Text::clean($row->notes));
                    $sheet->setCellValue('I'.$rowIndex, (float) $row->q1);
                    $sheet->setCellValue('J'.$rowIndex, (float) $row->price1);
                    $sheet->setCellValue('K'.$rowIndex, (float) $row->sub_tot);
                    $rowIndex++;
                }

                $sheet->getStyle('A'.self::HEADER_ROW.':K'.self::HEADER_ROW)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFE8E1E1');
                $sheet->getStyle('A'.self::DATA_START_ROW.':A'.($rowIndex - 1))
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('C'.self::DATA_START_ROW.':C'.($rowIndex - 1))
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('J'.self::DATA_START_ROW.':K'.($rowIndex - 1))
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            },
        ];
    }
}
