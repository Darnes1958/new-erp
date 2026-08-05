@include('filament.ins.modals.partials.contract-report-list', [
    'contract' => $contract,
    'rows' => $surpluses,
    'emptyMessage' => 'لا توجد أقساط بالفائض.',
    'countLabel' => 'عدد الأقساط',
    'totalLabel' => 'إجمالي الفائض',
    'dateField' => 'surplus_date',
    'amountField' => 'amount',
    'statusField' => 'status',
    'detailColumnLabel' => 'الحالة',
])
