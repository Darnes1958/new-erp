<?php

namespace App\Filament\Market\Resources\PurchaseInvoices\Schemas;

use App\Support\CompanySettings;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseInvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        [$lineTableColumns, $lineSchema] = static::lineTableDefinition();

        return $schema->components([
            Section::make('بيانات الفاتورة')->schema([
                TextEntry::make('id')->label('الرقم'),
                TextEntry::make('invoice_date')->label('التاريخ')->date(),
                TextEntry::make('supplier.name')->label('المورد'),
                TextEntry::make('paymentMethod.name')->label('طريقة الدفع'),
                TextEntry::make('warehouse.name')->label('المخزن'),
                TextEntry::make('lines_subtotal')->label('إجمالي البنود')->numeric(3),
                TextEntry::make('discount')->label('خصم')->numeric(3),
                TextEntry::make('amount_paid')->label('المدفوع')->numeric(3),
                TextEntry::make('balance')->label('الباقي')->numeric(3),
                TextEntry::make('notes')->label('ملاحظات')->columnSpanFull(),
            ])->columns(3),

            Section::make('البنود')->schema([
                RepeatableEntry::make('lines')
                    ->hiddenLabel()
                    ->table($lineTableColumns)
                    ->schema($lineSchema)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    /** @return array{0: array<TableColumn>, 1: array<TextEntry>} */
    protected static function lineTableDefinition(): array
    {
        $tableColumns = [
            TableColumn::make('الصنف'),
            TableColumn::make('الكمية'),
        ];

        $schema = [
            TextEntry::make('item.name'),
            TextEntry::make('qty_primary')->numeric(3),
        ];

        if (CompanySettings::hasDualUnit()) {
            $tableColumns[] = TableColumn::make('الكمية 2');
            $schema[] = TextEntry::make('qty_secondary')->numeric(3);
        }

        $tableColumns[] = TableColumn::make('السعر');
        $tableColumns[] = TableColumn::make('الإجمالي');

        $schema[] = TextEntry::make('unit_cost_primary')->numeric(3);
        $schema[] = TextEntry::make('line_cost_total')->numeric(3);

        return [$tableColumns, $schema];
    }
}
