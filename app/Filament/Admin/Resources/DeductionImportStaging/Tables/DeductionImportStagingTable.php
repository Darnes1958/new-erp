<?php

namespace App\Filament\Admin\Resources\DeductionImportStaging\Tables;

use App\Services\Installments\DeductionBatchImportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeductionImportStagingTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $session = app(DeductionBatchImportService::class)->currentSession();

                if ($session === null) {
                    return $query->whereRaw('1 = 0');
                }

                return $query
                    ->where('import_session_id', $session['session_id'])
                    ->whereNull('deduction_batch_id')
                    ->with('payrollBank');
            })
            ->defaultSort('row_number')
            ->columns([
                TextColumn::make('row_number')
                    ->label('السطر')
                    ->sortable(),
                TextColumn::make('account_number')
                    ->label('رقم الحساب')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('customer_name')
                    ->label('الاسم')
                    ->searchable(),
                TextColumn::make('deduction_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('الخصم')
                    ->numeric(3),
                TextColumn::make('payrollBank.name')
                    ->label('المصرف'),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('لا توجد بيانات مستوردة')
            ->emptyStateDescription('ابدأ بإعداد الاستيراد ثم ارفع ملف Excel.');
    }
}
