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

class WarehouseStockExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    use RtlInstallmentSheet;

    private const HEADER_ROW = 8;

    private const DATA_START_ROW = 9;

    /**
     * @param  Collection<int, object>  $rows
     * @param  array{warehouse_cost_total: float}  $summary
     */
    public function __construct(
        protected Collection $rows,
        protected ?OurCompany $company,
        protected string $reportTitle,
        protected array $summary,
        protected ?string $warehouseName,
        protected bool $showCosts,
        protected bool $multiWarehouse,
    ) {}

    public function collection(): Collection
    {
        return collect();
    }

    public function map($row): array
    {
        $columns = [];

        if ($this->multiWarehouse) {
            $columns[] = Utf8Text::clean($row->warehouse_name);
        }

        $columns[] = (int) $row->item_id;
        $columns[] = Utf8Text::clean($row->item_name);
        $columns[] = (float) $row->total_qty_primary;

        if ($this->multiWarehouse) {
            $columns[] = (float) $row->warehouse_qty_primary;
        }

        if ($this->showCosts) {
            $columns[] = (float) $row->total_cost_all;
            $columns[] = (float) $row->avg_unit_cost;
            $columns[] = (float) $row->catalog_buy_price;
            $columns[] = (float) $row->warehouse_cost_total;
        }

        $columns[] = (float) $row->sell_price_primary;

        return $columns;
    }

    public function headings(): array
    {
        $header = [];

        if ($this->multiWarehouse) {
            $header[] = 'المكان';
        }

        $header[] = 'رقم الصنف';
        $header[] = 'اسم الصنف';
        $header[] = 'الرصيد الكلي';

        if ($this->multiWarehouse) {
            $header[] = 'رصيد المكان';
        }

        if ($this->showCosts) {
            $header[] = 'التكلفة الكلية';
            $header[] = 'متوسط السعر';
            $header[] = 'سعر الشراء';
            $header[] = 'تكلفة المكان';
        }

        $header[] = 'سعر البيع';

        return [
            [Utf8Text::clean($this->company?->CompanyName)],
            [Utf8Text::clean($this->company?->CompanyNameSuffix)],
            [''],
            [Utf8Text::clean($this->reportTitle)],
            [$this->warehouseName ? Utf8Text::clean('المكان: '.$this->warehouseName) : ''],
            [''],
            [''],
            $header,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $this->applyRtlSheet($sheet);

                $rowIndex = self::DATA_START_ROW;

                foreach ($this->rows as $row) {
                    $column = 'A';

                    if ($this->multiWarehouse) {
                        $sheet->setCellValue($column++.$rowIndex, Utf8Text::clean($row->warehouse_name));
                    }

                    $sheet->setCellValue($column++.$rowIndex, (int) $row->item_id);
                    $sheet->setCellValue($column++.$rowIndex, Utf8Text::clean($row->item_name));
                    $sheet->setCellValue($column++.$rowIndex, (float) $row->total_qty_primary);

                    if ($this->multiWarehouse) {
                        $sheet->setCellValue($column++.$rowIndex, (float) $row->warehouse_qty_primary);
                    }

                    if ($this->showCosts) {
                        $sheet->setCellValue($column++.$rowIndex, (float) $row->total_cost_all);
                        $sheet->setCellValue($column++.$rowIndex, (float) $row->avg_unit_cost);
                        $sheet->setCellValue($column++.$rowIndex, (float) $row->catalog_buy_price);
                        $sheet->setCellValue($column++.$rowIndex, (float) $row->warehouse_cost_total);
                    }

                    $sheet->setCellValue($column.$rowIndex, (float) $row->sell_price_primary);
                    $rowIndex++;
                }

                if ($this->showCosts && $this->rows->isNotEmpty()) {
                    $totalColumn = $this->resolveTotalColumn();
                    $sheet->setCellValue('A'.$rowIndex, Utf8Text::clean('الإجمالي'));
                    $sheet->setCellValue(
                        $totalColumn.$rowIndex,
                        (float) $this->summary['warehouse_cost_total'],
                    );
                    $sheet->getStyle('A'.$rowIndex.':'.$totalColumn.$rowIndex)
                        ->getFont()
                        ->setBold(true);
                }

                $lastColumn = $this->resolveLastColumn();
                $sheet->getStyle('A'.self::HEADER_ROW.':'.$lastColumn.self::HEADER_ROW)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFE5E7EB');
                $sheet->getStyle('A'.self::HEADER_ROW.':'.$lastColumn.self::HEADER_ROW)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $numericStart = $this->multiWarehouse ? 'D' : 'C';
                $sheet->getStyle($numericStart.self::DATA_START_ROW.':'.$lastColumn.($rowIndex - 1))
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
            },
        ];
    }

    protected function resolveLastColumn(): string
    {
        $columns = 3;

        if ($this->multiWarehouse) {
            $columns += 2;
        }

        if ($this->showCosts) {
            $columns += 4;
        }

        $columns += 1;

        return chr(ord('A') + $columns - 1);
    }

    protected function resolveTotalColumn(): string
    {
        $offset = 3;

        if ($this->multiWarehouse) {
            $offset += 2;
        }

        if ($this->showCosts) {
            $offset += 3;
        }

        return chr(ord('A') + $offset);
    }
}
