<?php

namespace App\Filament\Ins\Pages\Reports;

use App\Enums\InstallmentDeductionType;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentDeductionArchive;
use App\Models\InstallmentSurplus;
use App\Models\InstallmentSuspended;
use App\Services\Installments\InstallmentContractArchiveService;
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

class ArchiveContractReportPage extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'استعلام عن عقد من الأرشيف';

    protected static ?string $title = 'استعلام عن عقد من الأرشيف';

    protected static ?string $slug = 'archive-contract-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'تقارير';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.ins.pages.reports.archive-contract-report';

    protected ?string $heading = '';

    public ?int $contractId = null;

    public ?string $contractNumber = null;

    public ?InstallmentContractArchive $archive = null;

    public int $customerArchiveContractsCount = 0;

    public int $customerOtherActiveContractsCount = 0;

    public ?int $selectedArchiveContractId = null;

    public ?int $selectedActiveContractId = null;

    public static function getNavigationBadge(): ?string
    {
        $count = InstallmentContractArchive::query()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        if (! ($user?->is_prog || $user?->can('تقرير عن عقد من الارشيف'))) {
            return false;
        }

        return InstallmentContractArchive::query()->exists();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('تقرير عن عقد من الارشيف');
    }

    public function mount(): void
    {
        $firstId = InstallmentContractArchive::query()->min('id');

        if (! $firstId) {
            return;
        }

        $this->loadArchive((int) $firstId);

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
                    ->noSearchResultsMessage('لا توجد عقود مؤرشفة مطابقة')
                    ->getSearchResultsUsing(fn (string $search): array => $this->searchArchiveOptions($search))
                    ->getOptionLabelUsing(fn ($value): ?string => $this->archiveOptionLabel(is_numeric($value) ? (int) $value : null))
                    ->live()
                    ->afterStateUpdated(function (?int $state, Set $set): void {
                        if ($state) {
                            $this->loadArchive($state);
                            $set('contractNumber', (string) $state);
                        }
                    }),
                TextInput::make('contractNumber')
                    ->label('رقم العقد')
                    ->live(onBlur: true)
                    ->extraInputAttributes([
                        'wire:keydown.enter.prevent' => '$wire.loadArchiveFromInput($event.target.value)',
                    ])
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if (blank($state)) {
                            return;
                        }

                        if (InstallmentContractArchive::query()->whereKey($state)->exists()) {
                            $this->loadArchive((int) $state);
                            $set('contractId', (int) $state);
                        }
                    }),
                Actions::make([
                    Action::make('restore')
                        ->label('استرجاع من الأرشيف')
                        ->color('success')
                        ->size(Size::Small)
                        ->requiresConfirmation()
                        ->modalHeading('استرجاع العقد من الأرشيف')
                        ->modalDescription('سيتم إعادة العقد إلى قائمة العقود النشطة مع خصوماته.')
                        ->visible(fn (): bool => $this->archive !== null)
                        ->action(fn () => $this->restoreCurrentArchive()),
                ])
                    ->columnSpan(2)
                    ->extraAttributes(['class' => 'ins-compact-exports']),
            ])
            ->columns(6);
    }

    public function archiveInfolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('customer.name')
                    ->state(fn (): ?string => $this->archive?->customer?->name)
                    ->label(new HtmlString('<div class="text-primary-600 dark:text-primary-400 text-lg font-extrabold">اسم الزبون</div>'))
                    ->color('info')
                    ->size(TextSize::Large)
                    ->weight(FontWeight::ExtraBold)
                    ->columnSpan(3),
                TextEntry::make('installmentBank.name')
                    ->state(fn (): ?string => $this->archive?->installmentBank?->name)
                    ->label('المصرف')
                    ->columnSpan(3),
                TextEntry::make('bank_account_number')
                    ->state(fn (): ?string => $this->archive?->bank_account_number)
                    ->label('رقم الحساب')
                    ->columnSpan(2),
                TextEntry::make('id')
                    ->state(fn (): ?int => $this->archive?->id)
                    ->label(new HtmlString('<div class="text-primary-600 dark:text-primary-400 text-lg">رقم العقد</div>'))
                    ->color('info')
                    ->weight(FontWeight::ExtraBold)
                    ->size(TextSize::Large)
                    ->columnSpan(2),
                TextEntry::make('contract_start')
                    ->state(fn (): ?string => $this->archive?->contract_start?->format('Y-m-d'))
                    ->label('تاريخ العقد')
                    ->columnSpan(2),
                TextEntry::make('contract_total')
                    ->state(fn (): ?string => $this->archive ? number_format((float) $this->archive->contract_total, 3, '.', ',') : null)
                    ->label('قيمة العقد')
                    ->columnSpan(2),
                TextEntry::make('installment_count')
                    ->state(fn (): ?int => $this->archive?->installment_count)
                    ->label('عدد الأقساط')
                    ->columnSpan(2),
                TextEntry::make('installment_amount')
                    ->state(fn (): ?string => $this->archive ? number_format((float) $this->archive->installment_amount, 3, '.', ',') : null)
                    ->label('القسط')
                    ->columnSpan(2),
                TextEntry::make('total_paid')
                    ->state(fn (): ?string => $this->archive ? number_format((float) $this->archive->total_paid, 3, '.', ',') : null)
                    ->label('المدفوع')
                    ->columnSpan(2),
                TextEntry::make('balance')
                    ->state(fn (): ?string => $this->archive ? number_format((float) $this->archive->balance, 3, '.', ',') : null)
                    ->label('المتبقي')
                    ->color('danger')
                    ->weight(FontWeight::ExtraBold)
                    ->columnSpan(2),
                TextEntry::make('archived_at')
                    ->state(fn (): ?string => $this->archive?->archived_at?->format('Y-m-d'))
                    ->label('تاريخ الأرشفة')
                    ->columnSpan(2),
                TextEntry::make('last_deduction_date')
                    ->state(fn (): ?string => $this->archiveLastDeductionDate())
                    ->label('تاريخ آخر خصم')
                    ->visible(fn (): bool => filled($this->archiveLastDeductionDate()))
                    ->columnSpan(2),
                Section::make()
                    ->schema([
                        TextEntry::make('archive_adjustments')
                            ->hiddenLabel()
                            ->state(fn (): ?HtmlString => $this->archiveAdjustmentsBlock())
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->visible(fn (): bool => $this->archiveSurplusCount() > 0 || $this->archiveReturnsCount() > 0)
                    ->extraAttributes(['class' => 'ins-contract-adjustments ins-contract-adjustments--lead']),
                TextEntry::make('notes')
                    ->state(fn (): ?string => $this->archive?->notes)
                    ->label('ملاحظات')
                    ->visible(fn (): bool => filled($this->archive?->notes))
                    ->columnSpanFull(),
            ])
            ->columns(8);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => InstallmentDeductionArchive::query()
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
        return Action::make('viewSurpluses')
            ->modalHeading('كشف الأقساط بالفائض')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalContent(fn () => view('filament.ins.modals.contract-surpluses', [
                'contract' => $this->archive,
                'surpluses' => $this->getArchiveSurpluses(),
            ]));
    }

    public function viewReturnsAction(): Action
    {
        return Action::make('viewReturns')
            ->modalHeading('كشف الأقساط المرجعة')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalContent(fn () => view('filament.ins.modals.contract-returns', [
                'contract' => $this->archive,
                'returns' => $this->getArchiveReturns(),
            ]));
    }

    public function viewArchiveContractsAction(): Action
    {
        return Action::make('viewArchiveContracts')
            ->modalHeading('عقود الزبون في الأرشيف')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->modalWidth(Width::FiveExtraLarge)
            ->mountUsing(function (): void {
                $this->selectedArchiveContractId = $this->getCustomerOtherArchiveContracts()->first()?->id;
            })
            ->modalContent(fn () => view('filament.ins.modals.contract-archives', [
                'customer' => $this->archive?->customer,
                'archives' => $this->getCustomerOtherArchiveContracts(),
                'selectedArchive' => $this->getSelectedOtherArchiveContract(),
                'selectedArchiveContractId' => $this->selectedArchiveContractId,
            ]));
    }

    public function viewActiveContractsAction(): Action
    {
        return Action::make('viewActiveContracts')
            ->modalHeading('عقود الزبون القائمة')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->modalWidth(Width::FiveExtraLarge)
            ->mountUsing(function (): void {
                $this->selectedActiveContractId = $this->getCustomerActiveContracts()->first()?->id;
            })
            ->modalContent(fn () => view('filament.ins.modals.contract-actives', [
                'customer' => $this->archive?->customer,
                'contracts' => $this->getCustomerActiveContracts(),
                'selectedContract' => $this->getSelectedActiveContract(),
                'selectedActiveContractId' => $this->selectedActiveContractId,
            ]));
    }

    public function selectArchiveContract(int $archiveContractId): void
    {
        if (! $this->getCustomerOtherArchiveContracts()->contains('id', $archiveContractId)) {
            return;
        }

        $this->selectedArchiveContractId = $archiveContractId;
    }

    public function selectActiveContract(int $contractId): void
    {
        if (! $this->getCustomerActiveContracts()->contains('id', $contractId)) {
            return;
        }

        $this->selectedActiveContractId = $contractId;
    }

    public function loadArchiveFromInput(?string $value): void
    {
        if (blank($value)) {
            return;
        }

        $this->loadArchive((int) $value);
        $this->contractSearchForm->fill([
            'contractId' => $this->contractId,
            'contractNumber' => (string) $this->contractId,
        ]);
    }

    public function loadArchive(?int $contractId): void
    {
        if (! $contractId) {
            $this->archive = null;
            $this->contractId = null;
            $this->contractNumber = null;
            $this->customerArchiveContractsCount = 0;
            $this->customerOtherActiveContractsCount = 0;
            $this->selectedArchiveContractId = null;
            $this->selectedActiveContractId = null;
            $this->resetTable();

            return;
        }

        $archive = InstallmentContractArchive::query()
            ->with(['customer', 'installmentBank', 'surpluses'])
            ->find($contractId);

        if (! $archive) {
            Notification::make()
                ->title('هذا الرقم غير مخزون')
                ->danger()
                ->send();

            return;
        }

        $this->archive = $archive;
        $this->contractId = $archive->id;
        $this->contractNumber = (string) $archive->id;
        $this->customerArchiveContractsCount = InstallmentContractArchive::query()
            ->where('customer_id', $archive->customer_id)
            ->whereKeyNot($archive->id)
            ->count();
        $this->customerOtherActiveContractsCount = InstallmentContract::query()
            ->where('customer_id', $archive->customer_id)
            ->count();
        $this->selectedArchiveContractId = null;
        $this->selectedActiveContractId = null;
        $this->resetTable();
    }

    protected function restoreCurrentArchive(): void
    {
        if (! $this->archive) {
            return;
        }

        $restoredId = $this->archive->id;

        try {
            app(InstallmentContractArchiveService::class)
                ->restoreToActive($this->archive);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?? 'تعذر الاسترجاع')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('تم استرجاع العقد بنجاح')
            ->success()
            ->send();

        $nextId = InstallmentContractArchive::query()
            ->whereKeyNot($restoredId)
            ->min('id');

        if ($nextId) {
            $this->loadArchive((int) $nextId);
        } else {
            $this->loadArchive(null);
        }

        $this->contractSearchForm->fill([
            'contractId' => $this->contractId,
            'contractNumber' => $this->contractNumber,
        ]);
    }

    /**
     * @return array<int|string, string>
     */
    protected function searchArchiveOptions(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        return InstallmentContractArchive::query()
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
            ->mapWithKeys(fn (InstallmentContractArchive $archive): array => [
                $archive->id => $this->formatArchiveOption($archive),
            ])
            ->all();
    }

    protected function archiveOptionLabel(?int $contractId): ?string
    {
        if (! $contractId) {
            return null;
        }

        $archive = InstallmentContractArchive::query()
            ->with('customer')
            ->find($contractId);

        return $archive ? $this->formatArchiveOption($archive) : null;
    }

    protected function formatArchiveOption(InstallmentContractArchive $archive): string
    {
        return sprintf(
            '%s — %s (%s)',
            (string) $archive->id,
            (string) ($archive->customer?->name ?? ''),
            (string) ($archive->bank_account_number ?? ''),
        );
    }

    protected function archiveLastDeductionDate(): ?string
    {
        if (! $this->archive) {
            return null;
        }

        $date = $this->archive->deductions()
            ->orderByDesc('deduction_date')
            ->orderByDesc('sequence')
            ->value('deduction_date');

        if (blank($date)) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($date)->format('Y-m-d');
    }

    protected function archiveSurplusCount(): int
    {
        return $this->archive?->surpluses()->count() ?? 0;
    }

    protected function archiveSurplusAmount(): float
    {
        return (float) ($this->archive?->surpluses()->sum('amount') ?? 0);
    }

    protected function archiveReturnsCount(): int
    {
        if (! $this->archive) {
            return 0;
        }

        return InstallmentSuspended::query()
            ->where('contractable_type', $this->archive->getMorphClass())
            ->where('contractable_id', $this->archive->id)
            ->count();
    }

    protected function archiveReturnsAmount(): float
    {
        if (! $this->archive) {
            return 0;
        }

        return (float) InstallmentSuspended::query()
            ->where('contractable_type', $this->archive->getMorphClass())
            ->where('contractable_id', $this->archive->id)
            ->sum('amount');
    }

    protected function archiveAdjustmentsBlock(): ?HtmlString
    {
        if (! $this->archive) {
            return null;
        }

        $rows = [];

        if ($this->archiveSurplusCount() > 0) {
            $rows[] = $this->archiveAdjustmentRow(
                tone: 'surplus',
                action: 'viewSurpluses',
                title: 'أقساط بالفائض',
                count: (string) $this->archiveSurplusCount(),
                amount: number_format($this->archiveSurplusAmount(), 3, '.', ','),
            );
        }

        if ($this->archiveReturnsCount() > 0) {
            $rows[] = $this->archiveAdjustmentRow(
                tone: 'return',
                action: 'viewReturns',
                title: 'أقساط مرجعة',
                count: (string) $this->archiveReturnsCount(),
                amount: number_format($this->archiveReturnsAmount(), 3, '.', ','),
            );
        }

        if ($rows === []) {
            return null;
        }

        return new HtmlString(
            '<div class="ins-contract-adjustments-block">'.implode('', $rows).'</div>'
        );
    }

    protected function archiveAdjustmentRow(
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

    /**
     * @return Collection<int, InstallmentSurplus>
     */
    protected function getArchiveSurpluses(): Collection
    {
        if (! $this->archive) {
            return collect();
        }

        return $this->archive->surpluses()
            ->orderBy('surplus_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, InstallmentSuspended>
     */
    protected function getArchiveReturns(): Collection
    {
        if (! $this->archive) {
            return collect();
        }

        return InstallmentSuspended::query()
            ->where('contractable_type', $this->archive->getMorphClass())
            ->where('contractable_id', $this->archive->id)
            ->orderBy('suspended_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, InstallmentContractArchive>
     */
    protected function getCustomerOtherArchiveContracts(): Collection
    {
        if (! $this->archive?->customer_id) {
            return collect();
        }

        return InstallmentContractArchive::query()
            ->where('customer_id', $this->archive->customer_id)
            ->whereKeyNot($this->archive->id)
            ->orderByDesc('archived_at')
            ->orderByDesc('id')
            ->get();
    }

    protected function getSelectedOtherArchiveContract(): ?InstallmentContractArchive
    {
        if (! $this->selectedArchiveContractId) {
            return null;
        }

        return $this->getCustomerOtherArchiveContracts()
            ->firstWhere('id', $this->selectedArchiveContractId);
    }

    /**
     * @return Collection<int, InstallmentContract>
     */
    protected function getCustomerActiveContracts(): Collection
    {
        if (! $this->archive?->customer_id) {
            return collect();
        }

        return InstallmentContract::query()
            ->where('customer_id', $this->archive->customer_id)
            ->orderByDesc('contract_start')
            ->orderByDesc('id')
            ->get();
    }

    protected function getSelectedActiveContract(): ?InstallmentContract
    {
        if (! $this->selectedActiveContractId) {
            return null;
        }

        return $this->getCustomerActiveContracts()
            ->firstWhere('id', $this->selectedActiveContractId);
    }
}
