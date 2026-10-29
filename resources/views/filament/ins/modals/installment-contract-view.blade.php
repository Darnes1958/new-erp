@php
    use App\Enums\InstallmentDeductionType;

    $deductions = $contract?->deductions ?? collect();
    $money = fn ($value): string => number_format((float) $value, 3, '.', ',');
@endphp

@include('filament.ins.modals.partials.contract-modal-styles')

<style>
    .ins-contract-view-modal {
        display: flex;
        flex-wrap: wrap;
        gap: 1.25rem;
        align-items: flex-start;
        direction: rtl;
    }

    .ins-contract-view-modal__pane {
        flex: 1 1 340px;
        min-width: 0;
    }

    .ins-contract-view-modal__details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.65rem 0.85rem;
    }

    .ins-contract-view-modal__detail--full {
        grid-column: 1 / -1;
    }

    .ins-contract-view-modal__label {
        display: block;
        color: rgb(107 114 128);
        font-size: 0.75rem;
        margin-bottom: 0.15rem;
    }

    .ins-contract-view-modal__value {
        display: block;
        color: rgb(17 24 39);
        font-size: 0.9rem;
        font-weight: 600;
        line-height: 1.35;
    }

    .ins-contract-view-modal__value--accent {
        color: rgb(37 99 235);
        font-size: 1.05rem;
        font-weight: 800;
    }

    .ins-contract-view-modal__value--danger {
        color: rgb(220 38 38);
        font-weight: 800;
    }

    .ins-contract-view-modal__toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.5rem;
    }

    .ins-contract-view-modal__title {
        font-size: 0.9rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .ins-contract-view-modal__columns {
        position: relative;
    }

    .ins-contract-view-modal__columns-btn {
        font-size: 0.8rem;
        font-weight: 600;
        color: rgb(55 65 81);
        background: rgb(249 250 251);
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        padding: 0.25rem 0.6rem;
        cursor: pointer;
    }

    .ins-contract-view-modal__columns-menu {
        position: absolute;
        inset-inline-end: 0;
        top: calc(100% + 0.35rem);
        z-index: 20;
        min-width: 10.5rem;
        background: #fff;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.65rem;
        box-shadow: 0 8px 20px rgb(15 23 42 / 12%);
        padding: 0.5rem 0.7rem;
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        font-size: 0.8rem;
    }

    .ins-contract-view-modal__columns-menu label {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        cursor: pointer;
        color: rgb(31 41 55);
    }

    .dark .ins-contract-view-modal__value,
    .dark .ins-contract-view-modal__title {
        color: rgb(243 244 246);
    }

    .dark .ins-contract-view-modal__label {
        color: rgb(156 163 175);
    }

    .dark .ins-contract-view-modal__columns-btn,
    .dark .ins-contract-view-modal__columns-menu {
        background: rgb(31 41 55);
        border-color: rgb(75 85 99);
        color: rgb(229 231 235);
    }
</style>

<div class="ins-contract-modal ins-contract-view-modal">
    <div class="ins-contract-view-modal__pane">
        <div class="ins-contract-view-modal__details">
            <div class="ins-contract-view-modal__detail--full">
                <span class="ins-contract-view-modal__label">اسم الزبون</span>
                <span class="ins-contract-view-modal__value ins-contract-view-modal__value--accent">{{ $contract?->customer?->name ?? '—' }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">رقم العقد</span>
                <span class="ins-contract-view-modal__value ins-contract-view-modal__value--accent">{{ $contract?->id ?? '—' }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">المصرف</span>
                <span class="ins-contract-view-modal__value">{{ $contract?->installmentBank?->name ?? '—' }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">رقم الحساب</span>
                <span class="ins-contract-view-modal__value">{{ $contract?->bank_account_number ?? '—' }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">جهة العمل</span>
                <span class="ins-contract-view-modal__value">{{ $contract?->workplace?->name ?? '—' }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">بداية العقد</span>
                <span class="ins-contract-view-modal__value">{{ $contract?->contract_start?->format('Y-m-d') ?? '—' }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">نهاية العقد</span>
                <span class="ins-contract-view-modal__value">{{ $contract?->contract_end?->format('Y-m-d') ?? '—' }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">قيمة العقد</span>
                <span class="ins-contract-view-modal__value">{{ $money($contract?->contract_total) }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">عدد الأقساط</span>
                <span class="ins-contract-view-modal__value">{{ $contract?->installment_count ?? '—' }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">قيمة القسط</span>
                <span class="ins-contract-view-modal__value">{{ $money($contract?->installment_amount) }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">المدفوع</span>
                <span class="ins-contract-view-modal__value">{{ $money($contract?->total_paid) }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">الباقي</span>
                <span class="ins-contract-view-modal__value ins-contract-view-modal__value--danger">{{ $money($contract?->balance) }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">أقساط متبقية</span>
                <span class="ins-contract-view-modal__value">{{ $contract?->installments_remaining ?? '—' }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">قسط قادم</span>
                <span class="ins-contract-view-modal__value">{{ $contract?->next_installment_date?->format('Y-m-d') ?? '—' }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">متأخرات</span>
                <span class="ins-contract-view-modal__value">{{ $money($contract?->late_amount) }}</span>
            </div>
            <div>
                <span class="ins-contract-view-modal__label">فاتورة البيع</span>
                <span class="ins-contract-view-modal__value">{{ $contract?->salesInvoice?->id ?? '—' }}</span>
            </div>
            @if (filled($contract?->notes))
                <div class="ins-contract-view-modal__detail--full">
                    <span class="ins-contract-view-modal__label">ملاحظات</span>
                    <span class="ins-contract-view-modal__value">{{ $contract->notes }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="ins-contract-view-modal__pane" x-data="{ open: false, due: false, method: false, notes: false }">
        <div class="ins-contract-view-modal__toolbar">
            <div class="ins-contract-view-modal__title">كشف الأقساط المخصومة</div>
            <div class="ins-contract-view-modal__columns">
                <button type="button" class="ins-contract-view-modal__columns-btn" @click="open = ! open">
                    أعمدة
                </button>
                <div class="ins-contract-view-modal__columns-menu" x-show="open" x-cloak @click.outside="open = false">
                    <label><input type="checkbox" x-model="due"> تاريخ القسط</label>
                    <label><input type="checkbox" x-model="method"> طريقة الدفع</label>
                    <label><input type="checkbox" x-model="notes"> ملاحظات</label>
                </div>
            </div>
        </div>

        @if ($deductions->isEmpty())
            <div class="ins-contract-modal__empty">لا توجد خصومات لهذا العقد.</div>
        @else
            <div class="ins-contract-modal__table-wrap">
                <table class="ins-contract-modal__table">
                    <thead>
                        <tr>
                            <th>ت</th>
                            <th>تاريخ الخصم</th>
                            <th x-show="due" x-cloak>تاريخ القسط</th>
                            <th>الخصم</th>
                            <th x-show="method" x-cloak>طريقة الدفع</th>
                            <th x-show="notes" x-cloak>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deductions as $deduction)
                            <tr>
                                <td>{{ $deduction->sequence }}</td>
                                <td>{{ $deduction->deduction_date?->format('Y-m-d') ?? '—' }}</td>
                                <td x-show="due" x-cloak>{{ $deduction->installment_due_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="ins-contract-modal__amount">{{ $money($deduction->deducted_amount) }}</td>
                                <td x-show="method" x-cloak>{{ InstallmentDeductionType::tryFrom((int) $deduction->deduction_type_id)?->getLabel() ?? '—' }}</td>
                                <td x-show="notes" x-cloak>{{ $deduction->notes ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
