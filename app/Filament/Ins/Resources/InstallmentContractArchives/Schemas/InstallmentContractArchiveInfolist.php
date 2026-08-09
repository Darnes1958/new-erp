<?php

namespace App\Filament\Ins\Resources\InstallmentContractArchives\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstallmentContractArchiveInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات العقد المؤرشف')->schema([
                TextEntry::make('id')->label('رقم العقد'),
                TextEntry::make('customer.name')->label('الزبون'),
                TextEntry::make('installmentBank.name')->label('المصرف'),
                TextEntry::make('bank_account_number')->label('رقم الحساب'),
                TextEntry::make('contract_start')->label('بداية العقد')->date(),
                TextEntry::make('contract_end')->label('نهاية العقد')->date(),
                TextEntry::make('contract_total')->label('قيمة العقد')->numeric(3),
                TextEntry::make('installment_count')->label('عدد الأقساط'),
                TextEntry::make('installment_amount')->label('قيمة القسط')->numeric(3),
                TextEntry::make('total_paid')->label('المدفوع')->numeric(3),
                TextEntry::make('balance')->label('الباقي')->numeric(3),
                TextEntry::make('archived_at')->label('تاريخ الأرشفة')->date(),
                TextEntry::make('notes')->label('ملاحظات')->columnSpanFull(),
            ])->columns(3),
        ]);
    }
}
