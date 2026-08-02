<?php

namespace App\Filament\Ins\Pages;

use App\Enums\InstallmentDeductionType;
use App\Models\InstallmentBank;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentDeduction;
use App\Models\PayrollBank;
use App\Services\Installments\InstallmentDeductionService;
use App\Support\CompanySettings;
use App\Support\InstallmentBankScope;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class RecordInstallmentDeduction extends Page implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'أقساط';

    protected static ?string $slug = 'record-installment-deduction';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'خصومات ومدفوعات';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.ins.pages.record-installment-deduction';

    protected ?string $heading = '';

    public array $deductionData = [];

    public ?int $contractId = null;

    public bool $isArchive = false;

    public bool $accountResolved = false;

    public ?string $statusMessage = null;

    public string $statusColor = 'danger';

    public bool $hasPartialDeduction = false;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->is_prog) {
            return true;
        }

        return $user->can('ادخال عقود') || $user->can('تعديل عقود');
    }

    public function mount(): void
    {
        $this->deductionData = $this->defaultDeductionData();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultDeductionData(): array
    {
        return [
            'payroll_bank_id' => null,
            'installment_bank_id' => null,
            'deduction_type_id' => InstallmentDeductionType::Bank->value,
            'deduction_date' => now()->toDateString(),
            'bank_account_number' => null,
            'installment_contract_id' => null,
            'deducted_amount' => null,
            'notes' => null,
        ];
    }

    protected function applyDeductionDefaults(): void
    {
        $this->deductionData['deduction_type_id'] = InstallmentDeductionType::Bank->value;
        $this->deductionData['deduction_date'] = now()->toDateString();
    }

    public function deductionForm(Schema $schema): Schema
    {
        return $schema
            ->model(InstallmentDeduction::class)
            ->statePath('deductionData')
            ->components([
                Section::make()
                    ->schema([
                        Select::make('payroll_bank_id')
                            ->label('المصرف')
                            ->options(fn (): array => PayrollBank::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->columnSpanFull()
                            ->visible(fn (): bool => CompanySettings::installmentByPayrollBank())
                            ->afterStateUpdated(function (?int $state, Set $set): void {
                                $branch = $state ? InstallmentBankScope::branchForPayroll($state) : null;
                                $set('installment_bank_id', $branch?->id);
                            }),
                        Select::make('installment_bank_id')
                            ->label('المصرف')
                            ->options(fn (): array => InstallmentBank::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->columnSpanFull()
                            ->visible(fn (): bool => ! CompanySettings::installmentByPayrollBank()),
                        Radio::make('deduction_type_id')
                            ->hiddenLabel()
                            ->options(InstallmentDeductionType::class)
                            ->default(InstallmentDeductionType::Bank->value)
                            ->inline()
                            ->inlineLabel()
                            ->columnSpanFull()
                            ->required(),
                        TextInput::make('bank_account_number')
                            ->label('رقم الحساب')
                            ->columnSpan(3)
                            ->autofocus()
                            ->id('bank_account_number')
                            ->live(onBlur: true)
                            ->extraInputAttributes([
                                'wire:keydown.enter.prevent' => 'resolveAccountFromEnter',
                            ])
                            ->afterStateUpdated(function (): void {
                                $this->accountResolved = false;
                                $this->contractId = null;
                                $this->isArchive = false;
                                $this->statusMessage = null;
                            }),
                        Select::make('installment_contract_id')
                            ->label('رقم العقد')
                            ->columnSpan(3)
                            ->searchable()
                            ->live()
                            ->id('installment_contract_id')
                            ->options(fn (): array => $this->contractOptions())
                            ->afterStateUpdated(function (?int $state): void {
                                if ($state) {
                                    $this->contractId = $state;
                                    $this->isArchive = false;
                                    $this->accountResolved = true;
                                    $this->loadContractIntoForm();
                                    $this->focusField('deduction_date');
                                }
                            }),
                        Text::make('status')
                            ->columnSpanFull()
                            ->hidden(fn (): bool => blank($this->statusMessage))
                            ->content(fn (): HtmlString => new HtmlString(
                                '<span class="text-'.$this->statusColor.'-600 dark:text-'.$this->statusColor.'-400 font-medium">'
                                .e($this->statusMessage).
                                '</span>'
                            )),
                        Grid::make(6)
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('customer_name')
                                    ->hiddenLabel()
                                    ->readOnly()
                                    ->columnSpan(3),
                                TextInput::make('bank_name')
                                    ->label('المصرف')
                                    ->readOnly()
                                    ->columnSpan(3),
                                TextInput::make('contract_total')
                                    ->label('قيمة العقد')
                                    ->readOnly()
                                    ->columnSpan(2),
                                TextInput::make('total_paid')
                                    ->label('المسدد')
                                    ->readOnly()
                                    ->columnSpan(2),
                                TextInput::make('balance')
                                    ->label('المتبقي')
                                    ->readOnly()
                                    ->columnSpan(2),
                                TextInput::make('installments_remaining')
                                    ->label('أقساط متبقية')
                                    ->readOnly()
                                    ->columnSpan(2),
                                TextInput::make('late_amount')
                                    ->label('متأخر')
                                    ->readOnly()
                                    ->columnSpan(2),
                                TextInput::make('next_installment_date')
                                    ->label('القسط القادم')
                                    ->readOnly()
                                    ->columnSpan(2),
                            ])
                            ->visible(fn (): bool => filled($this->contractId)),
                        DatePicker::make('deduction_date')
                            ->label('التاريخ')
                            ->required()
                            ->default(fn (): string => now()->toDateString())
                            ->id('deduction_date')
                            ->columnSpan(3)
                            ->extraInputAttributes([
                                'x-on:keydown.enter.prevent' => '$wire.focusField(\'deducted_amount\')',
                            ]),
                        TextInput::make('deducted_amount')
                            ->label('القسط')
                            ->numeric()
                            ->required()
                            ->id('deducted_amount')
                            ->columnSpan(3)
                            ->extraInputAttributes([
                                'wire:keydown.enter.prevent' => 'storeDeduction',
                            ]),
                        TextInput::make('notes')
                            ->label('ملاحظات')
                            ->columnSpanFull(),
                        Section::make()
                            ->columnSpanFull()
                            ->schema([
                                \Filament\Schemas\Components\Actions::make([
                                    Action::make('storeDeduction')
                                        ->label('خصم')
                                        ->icon('heroicon-o-check')
                                        ->action('storeDeduction'),
                                    Action::make('resetForm')
                                        ->label('جديد')
                                        ->color('gray')
                                        ->action('resetEntryForm'),
                                ])->fullWidth(),
                            ]),
                    ])
                    ->columns(6),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => InstallmentDeduction::query()
                ->when(
                    $this->contractId && ! $this->isArchive,
                    fn (Builder $query) => $query->where('installment_contract_id', $this->contractId),
                    fn (Builder $query) => $query->whereRaw('1 = 0'),
                ))
            ->defaultSort('sequence')
            ->defaultPaginationPageOption(12)
            ->paginationPageOptions([5, 12, 15, 50, 'all'])
            ->emptyStateHeading('لا توجد أقساط مخصومة')
            ->emptyStateDescription('لم يتم خصم أقساط بعد')
            ->columns([
                TextColumn::make('sequence')
                    ->label('ت')
                    ->size(TextSize::ExtraSmall)
                    ->sortable(),
                TextColumn::make('installment_due_date')
                    ->label('ت. الاستحقاق')
                    ->date('Y-m-d')
                    ->size(TextSize::ExtraSmall)
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('deduction_date')
                    ->label('ت. الخصم')
                    ->date('Y-m-d')
                    ->size(TextSize::ExtraSmall)
                    ->sortable(),
                TextColumn::make('deducted_amount')
                    ->label('الخصم')
                    ->numeric(3)
                    ->size(TextSize::ExtraSmall),
                TextColumn::make('remaining_balance')
                    ->label('الباقي')
                    ->numeric(3)
                    ->size(TextSize::ExtraSmall)
                    ->color('success')
                    ->visible(fn (): bool => $this->hasPartialDeduction),
                TextColumn::make('deduction_type_id')
                    ->label('طريقة الدفع')
                    ->formatStateUsing(fn ($state) => InstallmentDeductionType::tryFrom((int) $state)?->getLabel() ?? $state)
                    ->size(TextSize::ExtraSmall)
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->size(TextSize::ExtraSmall)
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->icon('heroicon-o-pencil')
                    ->iconButton()
                    ->iconSize(IconSize::Small)
                    ->color('info')
                    ->visible(fn (): bool => ! $this->isArchive && ! $this->hasPartialDeduction)
                    ->fillForm(fn (InstallmentDeduction $record): array => [
                        'deduction_date' => $record->deduction_date,
                        'deducted_amount' => $record->deducted_amount,
                        'notes' => $record->notes,
                        'deduction_type_id' => $record->deduction_type_id,
                    ])
                    ->schema([
                        Radio::make('deduction_type_id')
                            ->options(InstallmentDeductionType::class)
                            ->inline()
                            ->required(),
                        DatePicker::make('deduction_date')
                            ->label('التاريخ')
                            ->required(),
                        TextInput::make('deducted_amount')
                            ->label('القسط')
                            ->numeric()
                            ->required(),
                        TextInput::make('notes')
                            ->label('ملاحظات')
                            ->columnSpanFull(),
                    ])
                    ->modalHeading('تعديل قسط')
                    ->modalSubmitActionLabel('حفظ')
                    ->action(function (array $data, InstallmentDeduction $record): void {
                        app(InstallmentDeductionService::class)->update(
                            $record,
                            $data['deduction_date'],
                            (float) $data['deducted_amount'],
                            (int) $data['deduction_type_id'],
                            $data['notes'] ?? null,
                        );

                        $this->loadContractIntoForm();
                        $this->resetTable();

                        Notification::make()->title('تم تعديل القسط')->success()->send();
                    }),
                Action::make('delete')
                    ->icon('heroicon-o-trash')
                    ->iconButton()
                    ->iconSize(IconSize::Small)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (InstallmentDeduction $record): bool => ! $this->isArchive && (float) $record->remaining_balance <= 0)
                    ->action(function (InstallmentDeduction $record): void {
                        app(InstallmentDeductionService::class)->delete($record);

                        $this->loadContractIntoForm();
                        $this->resetTable();

                        Notification::make()->title('تم حذف القسط')->success()->send();
                    }),
            ]);
    }

    public function resolveAccountFromEnter(): void
    {
        $this->resolveAccount();

        if ($this->contractId) {
            $this->focusField('deduction_date');
        }
    }

    public function focusField(string $field): void
    {
        $this->dispatch('focus-field', field: $field);
    }

    public function resolveAccount(): void
    {
        $account = trim((string) ($this->deductionData['bank_account_number'] ?? ''));

        $this->statusMessage = null;
        $this->isArchive = false;
        $this->contractId = null;
        $this->accountResolved = false;

        if ($account === '') {
            return;
        }

        $matches = InstallmentContract::query()
            ->where('bank_account_number', $account)
            ->tap(fn (Builder $query) => $this->applyBankScope($query))
            ->get();

        if ($matches->count() === 1) {
            $this->contractId = (int) $matches->first()->id;
            $this->accountResolved = true;
            $this->loadContractIntoForm();

            return;
        }

        if ($matches->count() > 1) {
            $this->statusMessage = 'يوجد أكثر من عقد لهذا الحساب .. يجب اختيار رقم العقد من القائمة';
            $this->statusColor = 'danger';

            return;
        }

        $archive = InstallmentContractArchive::query()
            ->where('bank_account_number', $account)
            ->tap(fn (Builder $query) => $this->applyBankScope($query))
            ->first();

        if ($archive) {
            $this->contractId = (int) $archive->id;
            $this->isArchive = true;
            $this->accountResolved = true;
            $this->statusMessage = 'خصم قسط بالفائض من الأرشيف';
            $this->statusColor = 'success';
            $this->loadContractIntoForm();

            return;
        }

        $this->statusMessage = 'لم يتم العثور على عقد لهذا الحساب';
        $this->statusColor = 'danger';
    }

    public function storeDeduction(InstallmentDeductionService $service): void
    {
        $this->deductionForm->validate();

        if (! $this->contractId) {
            Notification::make()->title('يجب اختيار العقد أولاً')->warning()->send();

            return;
        }

        $contract = $this->resolveContract();

        if (! $contract) {
            Notification::make()->title('العقد غير موجود')->danger()->send();

            return;
        }

        $data = $this->deductionData;

        $result = $service->record(
            $contract,
            $data['deduction_date'],
            (float) $data['deducted_amount'],
            (int) $data['deduction_type_id'],
            $data['notes'] ?? null,
        );

        Notification::make()
            ->title($result['message'])
            ->color($result['color'])
            ->send();

        $this->loadContractIntoForm();
        $this->resetTable();

        $this->applyDeductionDefaults();

        if ($contract instanceof InstallmentContract) {
            $this->deductionData['deducted_amount'] = (string) $contract->fresh()->installment_amount;
        }

        $this->deductionData['notes'] = null;

        $this->focusField('bank_account_number');
    }

    public function resetEntryForm(): void
    {
        $this->contractId = null;
        $this->isArchive = false;
        $this->accountResolved = false;
        $this->statusMessage = null;
        $this->statusColor = 'danger';
        $this->hasPartialDeduction = false;

        $this->deductionData = $this->defaultDeductionData();

        $this->focusField('bank_account_number');
    }

    protected function loadContractIntoForm(): void
    {
        $contract = $this->resolveContract();

        if (! $contract) {
            return;
        }

        if ($contract instanceof InstallmentContract) {
            $contract->refresh();

            if ((float) $contract->balance <= 0) {
                $this->statusMessage = 'خصم قسط بالفائض';
                $this->statusColor = 'warning';
            }

            $this->hasPartialDeduction = $contract->deductions()->where('remaining_balance', '>', 0)->exists();

            $this->deductionData = array_merge($this->deductionData, [
                'installment_contract_id' => $contract->id,
                'bank_account_number' => $contract->bank_account_number,
                'customer_name' => $contract->customer?->name,
                'bank_name' => $contract->installmentBank?->name,
                'contract_total' => number_format((float) $contract->contract_total, 3, '.', ','),
                'total_paid' => number_format((float) $contract->total_paid, 3, '.', ','),
                'balance' => number_format((float) $contract->balance, 3, '.', ','),
                'installments_remaining' => (string) $contract->installments_remaining,
                'late_amount' => (string) (int) $contract->late_amount,
                'next_installment_date' => $contract->next_installment_date?->toDateString(),
                'deducted_amount' => (string) $contract->installment_amount,
            ]);

            $this->applyDeductionDefaults();

            return;
        }

        $this->hasPartialDeduction = false;

        $this->deductionData = array_merge($this->deductionData, [
            'installment_contract_id' => $contract->id,
            'bank_account_number' => $contract->bank_account_number,
            'customer_name' => $contract->customer?->name,
            'bank_name' => $contract->installmentBank?->name ?? '',
            'contract_total' => number_format((float) $contract->contract_total, 3, '.', ','),
            'total_paid' => number_format((float) $contract->total_paid, 3, '.', ','),
            'balance' => number_format((float) $contract->balance, 3, '.', ','),
            'installments_remaining' => '0',
            'late_amount' => '0',
            'next_installment_date' => null,
            'deducted_amount' => (string) $contract->installment_amount,
        ]);

        $this->applyDeductionDefaults();
    }

    /**
     * @return array<int, string>
     */
    protected function contractOptions(): array
    {
        $account = trim((string) ($this->deductionData['bank_account_number'] ?? ''));

        return InstallmentContract::query()
            ->with('customer')
            ->when($account !== '', fn (Builder $query) => $query->where('bank_account_number', $account))
            ->tap(fn (Builder $query) => $this->applyBankScope($query))
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->mapWithKeys(fn (InstallmentContract $contract): array => [
                $contract->id => sprintf(
                    '%s %s %s %s',
                    (string) $contract->id,
                    (string) ($contract->customer?->name ?? ''),
                    number_format((float) $contract->contract_total, 3, '.', ','),
                    number_format((float) $contract->installment_amount, 3, '.', ','),
                ),
            ])
            ->all();
    }

    protected function resolveContract(): InstallmentContract|InstallmentContractArchive|null
    {
        if (! $this->contractId) {
            return null;
        }

        if ($this->isArchive) {
            return InstallmentContractArchive::query()
                ->with(['customer', 'installmentBank'])
                ->find($this->contractId);
        }

        return InstallmentContract::query()
            ->with(['customer', 'installmentBank'])
            ->find($this->contractId);
    }

    protected function applyBankScope(Builder $query): void
    {
        InstallmentBankScope::applyScope(
            $query,
            isset($this->deductionData['payroll_bank_id']) ? (int) $this->deductionData['payroll_bank_id'] : null,
            isset($this->deductionData['installment_bank_id']) ? (int) $this->deductionData['installment_bank_id'] : null,
        );
    }
}
