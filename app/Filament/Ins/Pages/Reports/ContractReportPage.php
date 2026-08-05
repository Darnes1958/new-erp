<?php

namespace App\Filament\Ins\Pages\Reports;

use App\Enums\InstallmentDeductionType;
use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentDeduction;
use App\Models\InstallmentSurplus;
use App\Models\InstallmentSuspended;
use App\Services\Installments\InstallmentContractArchiveService;
use App\Services\Pdf\InstallmentContractPdfService;
use App\Support\PdfDownload;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class ContractReportPage extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'تقرير عن عقد';

    protected static ?string $title = 'تقرير عن عقد';

    protected static ?string $slug = 'contract-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'تقارير';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.ins.pages.reports.contract-report';

    protected ?string $heading = '';

    public ?int $contractId = null;

    public ?string $contractNumber = null;

    public ?InstallmentContract $contract = null;

    public int $customerArchiveContractsCount = 0;

    public int $customerOtherActiveContractsCount = 0;

    public ?int $selectedArchiveContractId = null;

    public ?int $selectedActiveContractId = null;

    public static function getNavigationBadge(): ?string
    {
        $count = InstallmentContract::query()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('تقرير عن عقد');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $firstId = InstallmentContract::query()->min('id');

        if (! $firstId) {
            return;
        }

        $this->loadContract((int) $firstId);

        $this->contractSearchForm->fill([
            'contractId' => $this->contractId,
            'contractNumber' => $this->contractNumber,
        ]);
    }

    public function contractSearchForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('contractId')
                    ->label('بحث عن العقد')
                    ->columnSpan(3)
                    ->searchable()
                    ->searchPrompt('ابحث برقم العقد أو اسم الزبون أو الحساب...')
                    ->noSearchResultsMessage('لا توجد عقود مطابقة')
                    ->getSearchResultsUsing(fn (string $search): array => $this->searchContractOptions($search))
                    ->getOptionLabelUsing(fn ($value): ?string => $this->contractOptionLabel(is_numeric($value) ? (int) $value : null))
                    ->live()
                    ->afterStateUpdated(function (?int $state, Set $set): void {
                        if ($state) {
                            $this->loadContract($state);
                            $set('contractNumber', (string) $state);
                        }
                    }),
                TextInput::make('contractNumber')
                    ->label('رقم العقد')
                    ->live(onBlur: true)
                    ->extraInputAttributes([
                        'wire:keydown.enter.prevent' => '$wire.loadContractFromInput($event.target.value)',
                    ])
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if (blank($state)) {
                            return;
                        }

                        if (InstallmentContract::query()->whereKey($state)->exists()) {
                            $this->loadContract((int) $state);
                            $set('contractId', (int) $state);
                        }
                    }),
                Actions::make([
                    InstallmentListPrintActions::compactPrint('printSummary', fn () => $this->downloadSummaryPdf()),
                    Action::make('printForm')
                        ->label('نموذج')
                        ->color('info')
                        ->size(Size::Small)
                        ->action(fn () => $this->downloadContractFormPdf()),
                    Action::make('archive')
                        ->label('أرشيف')
                        ->color('primary')
                        ->size(Size::Small)
                        ->requiresConfirmation()
                        ->modalHeading('نقل العقد إلى الأرشيف')
                        ->modalDescription('سيتم نقل العقد وجميع خصوماته إلى الأرشيف.')
                        ->visible(fn (): bool => $this->contract !== null && (float) $this->contract->balance <= 0)
                        ->action(fn () => $this->archiveCurrentContract()),
                ])
                    ->columnSpan(2)
                    ->extraAttributes(['class' => 'ins-compact-exports']),
            ])
            ->columns(6);
    }

    public function contractInfolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('customer.name')
                    ->state(fn (): ?string => $this->contract?->customer?->name)
                    ->label(new HtmlString('<div class="text-primary-600 dark:text-primary-400 text-lg font-extrabold">اسم الزبون</div>'))
                    ->color('info')
                    ->size(TextSize::Large)
                    ->weight(FontWeight::ExtraBold)
                    ->columnSpan(3),
                TextEntry::make('installmentBank.name')
                    ->state(fn (): ?string => $this->contract?->installmentBank?->name)
                    ->label('المصرف')
                    ->columnSpan(3),
                TextEntry::make('bank_account_number')
                    ->state(fn (): ?string => $this->contract?->bank_account_number)
                    ->label('رقم الحساب')
                    ->columnSpan(2),
                TextEntry::make('id')
                    ->state(fn (): ?int => $this->contract?->id)
                    ->label(new HtmlString('<div class="text-primary-600 dark:text-primary-400 text-lg">رقم العقد</div>'))
                    ->color('info')
                    ->weight(FontWeight::ExtraBold)
                    ->size(TextSize::Large)
                    ->columnSpan(2),
                TextEntry::make('contract_start')
                    ->state(fn (): ?string => $this->contract?->contract_start?->format('Y-m-d'))
                    ->label('تاريخ العقد')
                    ->columnSpan(2),
                TextEntry::make('contract_total')
                    ->state(fn (): ?string => $this->contract ? number_format((float) $this->contract->contract_total, 3, '.', ',') : null)
                    ->label('قيمة العقد')
                    ->columnSpan(2),
                TextEntry::make('installment_count')
                    ->state(fn (): ?int => $this->contract?->installment_count)
                    ->label('عدد الأقساط')
                    ->columnSpan(2),
                TextEntry::make('installment_amount')
                    ->state(fn (): ?string => $this->contract ? number_format((float) $this->contract->installment_amount, 3, '.', ',') : null)
                    ->label('القسط')
                    ->columnSpan(2),
                TextEntry::make('total_paid')
                    ->state(fn (): ?string => $this->contract ? number_format((float) $this->contract->total_paid, 3, '.', ',') : null)
                    ->label('المدفوع')
                    ->columnSpan(2),
                TextEntry::make('balance')
                    ->state(fn (): ?string => $this->contract ? number_format((float) $this->contract->balance, 3, '.', ',') : null)
                    ->label('المتبقي')
                    ->color('danger')
                    ->weight(FontWeight::ExtraBold)
                    ->columnSpan(2),
                TextEntry::make('last_deduction_month')
                    ->state(fn (): ?string => $this->contract?->last_deduction_month?->format('Y-m-d'))
                    ->label('تاريخ آخر خصم')
                    ->visible(fn (): bool => filled($this->contract?->last_deduction_month))
                    ->columnSpan(2),
                Section::make()
                    ->schema([
                        TextEntry::make('contract_adjustments')
                            ->hiddenLabel()
                            ->state(fn (): ?HtmlString => $this->contractAdjustmentsBlock())
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->visible(fn (): bool => (int) ($this->contract?->surplus_count ?? 0) > 0
                        || (int) ($this->contract?->suspended_count ?? 0) > 0)
                    ->extraAttributes(['class' => 'ins-contract-adjustments ins-contract-adjustments--lead']),
                TextEntry::make('notes')
                    ->state(fn (): ?string => $this->contract?->notes)
                    ->label('ملاحظات')
                    ->visible(fn (): bool => filled($this->contract?->notes))
                    ->columnSpanFull(),
            ])
            ->columns(8);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => InstallmentDeduction::query()
                ->when(
                    $this->contractId,
                    fn (Builder $query): Builder => $query->where('installment_contract_id', $this->contractId),
                    fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
                ))
            ->columns([
                TextColumn::make('sequence')
                    ->label('ت')
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('installment_due_date')
                    ->label('تاريخ القسط')
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('deduction_date')
                    ->label('تاريخ الخصم')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('deducted_amount')
                    ->label('الخصم')
                    ->numeric(3),
                TextColumn::make('deduction_type_id')
                    ->label('طريقة الدفع')
                    ->formatStateUsing(fn ($state): ?string => InstallmentDeductionType::tryFrom((int) $state)?->getLabel())
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->defaultSort('sequence')
            ->emptyStateHeading('لا توجد خصومات لهذا العقد')
            ->striped();
    }

    public function viewSurplusesAction(): Action
    {
        return $this->makeViewSurplusesAction();
    }

    public function viewReturnsAction(): Action
    {
        return $this->makeViewReturnsAction();
    }

    public function viewArchiveContractsAction(): Action
    {
        return $this->makeViewArchiveContractsAction();
    }

    public function viewActiveContractsAction(): Action
    {
        return $this->makeViewActiveContractsAction();
    }

    protected function makeViewSurplusesAction(string $name = 'viewSurpluses'): Action
    {
        return Action::make($name)
            ->modalHeading('كشف الأقساط بالفائض')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalContent(fn () => view('filament.ins.modals.contract-surpluses', [
                'contract' => $this->contract,
                'surpluses' => $this->getContractSurpluses(),
            ]));
    }

    protected function makeViewReturnsAction(string $name = 'viewReturns'): Action
    {
        return Action::make($name)
            ->modalHeading('كشف الأقساط المرجعة')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalContent(fn () => view('filament.ins.modals.contract-returns', [
                'contract' => $this->contract,
                'returns' => $this->getContractReturns(),
            ]));
    }

    protected function makeViewArchiveContractsAction(string $name = 'viewArchiveContracts'): Action
    {
        return Action::make($name)
            ->modalHeading('عقود الزبون في الأرشيف')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->modalWidth(Width::FiveExtraLarge)
            ->mountUsing(function (): void {
                $this->selectedArchiveContractId = $this->getCustomerArchiveContracts()->first()?->id;
            })
            ->modalContent(fn () => view('filament.ins.modals.contract-archives', [
                'customer' => $this->contract?->customer,
                'archives' => $this->getCustomerArchiveContracts(),
                'selectedArchive' => $this->getSelectedArchiveContract(),
                'selectedArchiveContractId' => $this->selectedArchiveContractId,
            ]));
    }

    public function selectArchiveContract(int $archiveContractId): void
    {
        if (! $this->getCustomerArchiveContracts()->contains('id', $archiveContractId)) {
            return;
        }

        $this->selectedArchiveContractId = $archiveContractId;
    }

    protected function makeViewActiveContractsAction(string $name = 'viewActiveContracts'): Action
    {
        return Action::make($name)
            ->modalHeading('عقود الزبون القائمة')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->modalWidth(Width::FiveExtraLarge)
            ->mountUsing(function (): void {
                $this->selectedActiveContractId = $this->getCustomerOtherActiveContracts()->first()?->id;
            })
            ->modalContent(fn () => view('filament.ins.modals.contract-actives', [
                'customer' => $this->contract?->customer,
                'contracts' => $this->getCustomerOtherActiveContracts(),
                'selectedContract' => $this->getSelectedActiveContract(),
                'selectedActiveContractId' => $this->selectedActiveContractId,
            ]));
    }

    public function selectActiveContract(int $contractId): void
    {
        if (! $this->getCustomerOtherActiveContracts()->contains('id', $contractId)) {
            return;
        }

        $this->selectedActiveContractId = $contractId;
    }

    /**
     * @return Collection<int, InstallmentSurplus>
     */
    protected function getContractSurpluses(): Collection
    {
        if (! $this->contract) {
            return collect();
        }

        return $this->contract->surpluses()
            ->orderBy('surplus_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, InstallmentSuspended>
     */
    protected function getContractReturns(): Collection
    {
        if (! $this->contract) {
            return collect();
        }

        return InstallmentSuspended::query()
            ->where('installment_contract_id', $this->contract->id)
            ->orderBy('suspended_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, InstallmentContractArchive>
     */
    protected function getCustomerArchiveContracts(): Collection
    {
        if (! $this->contract?->customer_id) {
            return collect();
        }

        return InstallmentContractArchive::query()
            ->where('customer_id', $this->contract->customer_id)
            ->orderByDesc('archived_at')
            ->orderByDesc('id')
            ->get();
    }

    protected function getSelectedArchiveContract(): ?InstallmentContractArchive
    {
        if (! $this->selectedArchiveContractId) {
            return null;
        }

        return $this->getCustomerArchiveContracts()
            ->firstWhere('id', $this->selectedArchiveContractId);
    }

    /**
     * @return Collection<int, InstallmentContract>
     */
    protected function getCustomerOtherActiveContracts(): Collection
    {
        if (! $this->contract?->customer_id) {
            return collect();
        }

        return InstallmentContract::query()
            ->where('customer_id', $this->contract->customer_id)
            ->whereKeyNot($this->contract->id)
            ->orderByDesc('contract_start')
            ->orderByDesc('id')
            ->get();
    }

    protected function getSelectedActiveContract(): ?InstallmentContract
    {
        if (! $this->selectedActiveContractId) {
            return null;
        }

        return $this->getCustomerOtherActiveContracts()
            ->firstWhere('id', $this->selectedActiveContractId);
    }

    public function loadContractFromInput(?string $value): void
    {
        if (blank($value)) {
            return;
        }

        $this->loadContract((int) $value);
        $this->contractSearchForm->fill([
            'contractId' => $this->contractId,
            'contractNumber' => (string) $this->contractId,
        ]);
    }

    public function loadContract(?int $contractId): void
    {
        if (! $contractId) {
            $this->contract = null;
            $this->contractId = null;
            $this->contractNumber = null;
            $this->customerArchiveContractsCount = 0;
            $this->customerOtherActiveContractsCount = 0;
            $this->selectedArchiveContractId = null;
            $this->selectedActiveContractId = null;
            $this->resetTable();

            return;
        }

        $contract = InstallmentContract::query()
            ->with(['customer', 'installmentBank', 'payrollBank', 'surpluses'])
            ->find($contractId);

        if (! $contract) {
            Notification::make()
                ->title('هذا الرقم غير مخزون')
                ->danger()
                ->send();

            return;
        }

        $this->contract = $contract;
        $this->contractId = $contract->id;
        $this->contractNumber = (string) $contract->id;
        $this->customerArchiveContractsCount = InstallmentContractArchive::query()
            ->where('customer_id', $contract->customer_id)
            ->count();

        $this->customerOtherActiveContractsCount = InstallmentContract::query()
            ->where('customer_id', $contract->customer_id)
            ->whereKeyNot($contract->id)
            ->count();

        $this->selectedArchiveContractId = null;
        $this->selectedActiveContractId = null;

        $this->resetTable();
    }

    protected function downloadSummaryPdf(): mixed
    {
        if (! $this->contract) {
            Notification::make()
                ->title('اختر العقد أولاً')
                ->warning()
                ->send();

            return null;
        }

        $deductions = $this->contract->deductions()
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        return PdfDownload::streamed(
            app(InstallmentContractPdfService::class)->summaryReport($this->contract, $deductions),
        );
    }

    protected function downloadContractFormPdf(): mixed
    {
        if (! $this->contract) {
            Notification::make()
                ->title('اختر العقد أولاً')
                ->warning()
                ->send();

            return null;
        }

        return PdfDownload::streamed(
            app(InstallmentContractPdfService::class)->contractFormReport($this->contract),
        );
    }

    protected function archiveCurrentContract(): void
    {
        if (! $this->contract || (float) $this->contract->balance > 0) {
            Notification::make()
                ->title('لا يمكن نقل العقد قبل سداد الرصيد')
                ->warning()
                ->send();

            return;
        }

        $archivedId = $this->contract->id;

        try {
            app(InstallmentContractArchiveService::class)->moveFromActive($this->contract);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?? 'تعذر نقل العقد')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('تم النقل بنجاح')
            ->success()
            ->send();

        $nextId = InstallmentContract::query()
            ->where('id', '!=', $archivedId)
            ->min('id');

        if ($nextId) {
            $this->loadContract((int) $nextId);
        } else {
            $this->loadContract(null);
        }

        $this->contractSearchForm->fill([
            'contractId' => $this->contractId,
            'contractNumber' => $this->contractNumber,
        ]);
    }

    /**
     * @return array<int|string, string>
     */
    protected function searchContractOptions(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        return InstallmentContract::query()
            ->with('customer')
            ->where(function (Builder $query) use ($search): void {
                if (is_numeric($search)) {
                    $query->where('id', 'like', "{$search}%");
                }

                $query->orWhere('bank_account_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn (Builder $customerQuery): Builder => $customerQuery
                        ->where('name', 'like', "%{$search}%"));
            })
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->mapWithKeys(fn (InstallmentContract $contract): array => [
                $contract->id => $this->formatContractOption($contract),
            ])
            ->all();
    }

    protected function contractOptionLabel(?int $contractId): ?string
    {
        if (! $contractId) {
            return null;
        }

        $contract = InstallmentContract::query()
            ->with('customer')
            ->find($contractId);

        return $contract ? $this->formatContractOption($contract) : null;
    }

    protected function formatContractOption(InstallmentContract $contract): string
    {
        return sprintf(
            '%s — %s (%s)',
            (string) $contract->id,
            (string) ($contract->customer?->name ?? ''),
            (string) ($contract->bank_account_number ?? ''),
        );
    }

    protected function contractAdjustmentsBlock(): ?HtmlString
    {
        if (! $this->contract) {
            return null;
        }

        $rows = [];

        if ((int) $this->contract->surplus_count > 0) {
            $rows[] = $this->contractAdjustmentRow(
                tone: 'surplus',
                action: 'viewSurpluses',
                title: 'أقساط بالفائض',
                count: (string) (int) $this->contract->surplus_count,
                amount: number_format((float) $this->contract->surplus_amount, 3, '.', ','),
            );
        }

        if ((int) $this->contract->suspended_count > 0) {
            $rows[] = $this->contractAdjustmentRow(
                tone: 'return',
                action: 'viewReturns',
                title: 'أقساط مرجعة',
                count: (string) (int) $this->contract->suspended_count,
                amount: number_format((float) $this->contract->suspended_amount, 3, '.', ','),
            );
        }

        if ($rows === []) {
            return null;
        }

        return new HtmlString(
            '<div class="ins-contract-adjustments-block">'.implode('', $rows).'</div>'
        );
    }

    protected function contractAdjustmentRow(
        string $tone,
        string $action,
        string $title,
        string $count,
        string $amount,
    ): string {
        return sprintf(
            '<div class="ins-contract-adjustment-row ins-contract-adjustment-row--%1$s">
                <button type="button" class="ins-contract-adjustment-item ins-contract-adjustment-item--%1$s" wire:click="mountAction(\'%2$s\')">
                    <span class="ins-contract-adjustment-item__label">%3$s:</span>
                    <span class="ins-contract-adjustment-item__value">%4$s</span>
                </button>
                <button type="button" class="ins-contract-adjustment-item ins-contract-adjustment-item--%1$s" wire:click="mountAction(\'%2$s\')">
                    <span class="ins-contract-adjustment-item__label">قيمتها:</span>
                    <span class="ins-contract-adjustment-item__value">%5$s</span>
                </button>
            </div>',
            e($tone),
            e($action),
            e($title),
            e($count),
            e($amount),
        );
    }
}
