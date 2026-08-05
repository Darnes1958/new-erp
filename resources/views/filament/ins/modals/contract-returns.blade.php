@include('filament.ins.modals.partials.contract-report-list', [
    'contract' => $contract,
    'rows' => $returns,
    'emptyMessage' => 'لا توجد أقساط مرجعة.',
    'countLabel' => 'عدد الأقساط',
    'totalLabel' => 'إجمالي المرجع',
    'dateField' => 'suspended_date',
    'amountField' => 'amount',
    'statusField' => 'status',
    'detailColumnLabel' => 'البيان',
])
