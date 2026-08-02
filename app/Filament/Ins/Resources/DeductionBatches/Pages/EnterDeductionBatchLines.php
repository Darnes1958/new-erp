<?php

namespace App\Filament\Ins\Resources\DeductionBatches\Pages;

use App\Enums\DeductionBatchEntryType;
use App\Enums\DeductionBatchStatus;
use App\Enums\InstallmentDeductionType;
use App\Filament\Ins\Resources\DeductionBatches\DeductionBatchResource;
use App\Models\DeductionBatch;
use App\Models\DeductionBatchLine;
use App\Models\InstallmentContract;
use App\Services\Installments\DeductionBatchService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class EnterDeductionBatchLines extends Page implements HasSchemas, HasTable
{
    use InteractsWithRecord;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string $resource = DeductionBatchResource::class;

    protected static ?string $title = '';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.ins.pages.enter-deduction-batch-lines';

    public DeductionBatch $batch;

    public array $lineData = [];

    public ?int $contractId = null;

    public ?DeductionBatchEntryType $entryType = null;

    public ?string $statusMessage = null;

    public string $statusColor = 'danger';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->batch = $this->record->load(['payrollBank', 'installmentBank']);
        $this->lineData = $this->defaultLineData();
    }

    public function lineForm(Schema $schema): Schema
    {
        return $schema
            ->model(DeductionBatchLine::class)
            ->statePath('lineData')
            ->components([
                Section::make()
                    ->schema([
                        Text::make('batch_header')
                            ->columnSpanFull()
                            ->content(fn (): HtmlString => new HtmlString(
                                '<div class="space-y-1">'
                                .'<div class="text-lg font-bold">حافظة رقم <span style="color:#2563eb">'.e((string) $this->batch->id).'</span></div>'
                                .'<div class="font-semibold">المصرف: <span style="color:#2563eb">'.e($this->batch->branchDisplayName() ?? '—').'</span></div>'
                                .'</div>'
                            )),
                        Section::make()
                            ->columnSpanFull()
                            ->visible(fn (): bool => $this->batch->isOpen())
                            ->schema([
                                \Filament\Schemas\Components\Actions::make([
                                    Action::make('postBatch')
                                        ->label('ترحيل')
                                        ->icon('heroicon-o-arrow-up-tray')
                                        ->color('success')
                                        ->requiresConfirmation()
                                        ->modalHeading('ترحيل الحافظة')
                                        ->modalDescription('سيتم خصم جميع الأقساط وتأثيرها على العقود. هل تريد المتابعة؟')
                                        ->action('postBatch'),
                                ]),
                            ]),
                        Radio::make('deduction_type_id')
                            ->hiddenLabel()
                            ->options(InstallmentDeductionType::class)
                            ->default(InstallmentDeductionType::Bank->value)
                            ->inline()
                            ->inlineLabel()
                            ->columnSpanFull()
                            ->disabled(fn (): bool => ! $this->batch->isOpen()),
                        TextInput::make('bank_account_number')
                            ->label('رقم الحساب')
                            ->columnSpan(3)
                            ->autofocus()
                            ->id('batch_bank_account_number')
                            ->live(onBlur: true)
                            ->disabled(fn (): bool => ! $this->batch->isOpen())
                            ->afterStateUpdated(function (): void {
                                $this->contractId = null;
                                $this->lineData['installment_contract_id'] = null;
                                $this->statusMessage = null;
                            })
                            ->extraInputAttributes([
                                'wire:keydown.enter.prevent' => 'resolveAccountFromEnter',
                            ]),
                        Select::make('installment_contract_id')
                            ->label('رقم العقد')
                            ->columnSpan(3)
                            ->searchable()
                            ->id('batch_installment_contract_id')
                            ->disabled(fn (): bool => ! $this->batch->isOpen() || blank($this->lineData['bank_account_number'] ?? null))
                            ->options(fn (): array => $this->contractOptions())
                            ->placeholder(fn (): string => blank($this->lineData['bank_account_number'] ?? null)
                                ? 'أدخل رقم الحساب أولاً'
                                : 'اختر')
                            ->live()
                            ->afterStateUpdated(function (?int $state): void {
                                if ($state) {
                                    $this->contractId = $state;
                                    $this->loadContractPreview($state);
                                    $this->focusField('batch_deduction_date');
                                }
                            }),
                        Text::make('status')
                            ->columnSpanFull()
                            ->hidden(fn (): bool => blank($this->statusMessage))
                            ->content(fn (): HtmlString => new HtmlString(
                                '<span class="text-'.$this->statusColor.'-600 font-medium">'.e($this->statusMessage).'</span>'
                            )),
                        Grid::make(6)
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('customer_name')->hiddenLabel()->readOnly()->columnSpan(3),
                                TextInput::make('contract_total')->label('قيمة العقد')->readOnly()->columnSpan(2),
                                TextInput::make('balance')->label('المتبقي')->readOnly()->columnSpan(2),
                                TextInput::make('installments_remaining')->label('أقساط متبقية')->readOnly()->columnSpan(2),
                            ])
                            ->visible(fn (): bool => filled($this->contractId)),
                        DatePicker::make('deduction_date')
                            ->label('التاريخ')
                            ->required()
                            ->id('batch_deduction_date')
                            ->columnSpan(3)
                            ->disabled(fn (): bool => ! $this->batch->isOpen())
                            ->extraInputAttributes([
                                'x-on:keydown.enter.prevent' => '$wire.focusField(\'batch_deducted_amount\')',
                            ]),
                        TextInput::make('deducted_amount')
                            ->label('القسط')
                            ->numeric()
                            ->required()
                            ->id('batch_deducted_amount')
                            ->columnSpan(3)
                            ->disabled(fn (): bool => ! $this->batch->isOpen())
                            ->extraInputAttributes([
                                'wire:keydown.enter.prevent' => 'storeLine',
                            ]),
                        TextInput::make('notes')
                            ->label('ملاحظات')
                            ->columnSpanFull()
                            ->disabled(fn (): bool => ! $this->batch->isOpen()),
                        Section::make()
                            ->columnSpanFull()
                            ->visible(fn (): bool => $this->batch->isOpen())
                            ->schema([
                                \Filament\Schemas\Components\Actions::make([
                                    Action::make('storeLine')
                                        ->label('إضافة')
                                        ->icon('heroicon-o-plus')
                                        ->action('storeLine'),
                                    Action::make('wrongLine')
                                        ->label('بالخطأ')
                                        ->color('warning')
                                        ->schema([
                                            TextInput::make('name')->label('الاسم')->required(),
                                            DatePicker::make('deduction_date')->label('التاريخ')->required()->default(now()),
                                            TextInput::make('amount')->label('القسط')->numeric()->required(),
                                        ])
                                        ->action(function (array $data, DeductionBatchService $service): void {
                                            $account = trim((string) ($this->lineData['bank_account_number'] ?? ''));

                                            if ($account === '') {
                                                Notification::make()->title('يجب إدخال رقم الحساب أولاً')->warning()->send();

                                                return;
                                            }

                                            $service->addWrongLine(
                                                $this->batch,
                                                $account,
                                                $data['name'],
                                                (float) $data['amount'],
                                                $data['deduction_date'],
                                            );

                                            Notification::make()->title('تمت الإضافة')->success()->send();
                                            $this->resetLineEntry();
                                            $this->resetTable();
                                        }),
                                ]),
                            ]),
                    ])
                    ->columns(6),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => DeductionBatchLine::query()->where('deduction_batch_id', $this->batch->id))
            ->defaultSort('deduction_date', 'desc')
            ->columns([
                TextColumn::make('contractable_id')
                    ->label('العقد')
                    ->sortable(),
                TextColumn::make('account_number')
                    ->label('الحساب')
                    ->searchable(),
                TextColumn::make('contractable.customer.name')
                    ->label('الزبون')
                    ->placeholder(fn (DeductionBatchLine $record): ?string => $record->entry_type === DeductionBatchEntryType::Wrong ? $record->notes : null),
                TextColumn::make('amount')
                    ->label('القسط')
                    ->numeric(3)
                    ->summarize(Sum::make()->numeric(3)->label(' ')),
                TextColumn::make('deduction_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->recordActions([
                Action::make('deleteLine')
                    ->icon('heroicon-o-trash')
                    ->iconButton()
                    ->iconSize(IconSize::Small)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => $this->batch->isOpen())
                    ->action(function (DeductionBatchLine $record): void {
                        $record->delete();
                        Notification::make()->title('تم الحذف')->success()->send();
                    }),
            ]);
    }

    public function resolveAccountFromEnter(): void
    {
        try {
            $resolved = app(DeductionBatchService::class)->resolveContract(
                $this->batch,
                (string) ($this->lineData['bank_account_number'] ?? ''),
                $this->lineData['installment_contract_id'] ?? null,
            );

            $this->applyResolvedContract($resolved['contract'], $resolved['entry_type']);
            $this->focusField('batch_deduction_date');
        } catch (ValidationException $exception) {
            $this->statusMessage = collect($exception->errors())->flatten()->first();
            $this->statusColor = 'danger';
        }
    }

    public function storeLine(DeductionBatchService $service): void
    {
        $this->validate([
            'lineData.deduction_date' => ['required', 'date'],
            'lineData.deducted_amount' => ['required', 'numeric', 'min:0.001'],
            'lineData.bank_account_number' => ['required', 'string'],
        ]);

        try {
            $resolved = $service->resolveContract(
                $this->batch,
                (string) ($this->lineData['bank_account_number'] ?? ''),
                $this->contractId ?? $this->lineData['installment_contract_id'] ?? null,
            );

            $service->addLine(
                $this->batch,
                $resolved['contract'],
                $resolved['entry_type'],
                (string) $resolved['contract']->bank_account_number,
                (float) $this->lineData['deducted_amount'],
                $this->lineData['deduction_date'],
                $this->lineData['notes'] ?? null,
            );

            Notification::make()->title('تمت إضافة القسط للحافظة')->success()->send();
            $this->resetLineEntry();
            $this->resetTable();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?? 'خطأ')
                ->danger()
                ->send();
        }
    }

    public function postBatch(DeductionBatchService $service): void
    {
        try {
            $service->post($this->batch);
            $this->batch->refresh();

            Notification::make()->title('تم الترحيل بنجاح')->success()->send();

            redirect()->to(DeductionBatchResource::getUrl('index'));
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?? 'خطأ')
                ->danger()
                ->send();
        }
    }

    public function focusField(string $field): void
    {
        $this->dispatch('focus-field', field: $field);
    }

    protected function defaultLineData(): array
    {
        return [
            'deduction_type_id' => InstallmentDeductionType::Bank->value,
            'deduction_date' => now()->toDateString(),
            'bank_account_number' => null,
            'installment_contract_id' => null,
            'deducted_amount' => null,
            'notes' => null,
        ];
    }

    protected function resetLineEntry(): void
    {
        $this->contractId = null;
        $this->entryType = null;
        $this->statusMessage = null;
        $this->lineData = $this->defaultLineData();
        $this->focusField('batch_bank_account_number');
    }

    protected function applyResolvedContract($contract, DeductionBatchEntryType $entryType): void
    {
        $this->contractId = (int) $contract->id;
        $this->entryType = $entryType;
        $this->statusMessage = $entryType === DeductionBatchEntryType::Archive
            ? 'قسط من الأرشيف'
            : null;
        $this->statusColor = $entryType === DeductionBatchEntryType::Archive ? 'success' : 'info';

        $this->lineData = array_merge($this->lineData, [
            'installment_contract_id' => $contract->id,
            'bank_account_number' => $contract->bank_account_number,
            'customer_name' => $contract->customer?->name,
            'contract_total' => number_format((float) $contract->contract_total, 3, '.', ','),
            'balance' => number_format((float) $contract->balance, 3, '.', ','),
            'installments_remaining' => $contract instanceof InstallmentContract
                ? (string) $contract->installments_remaining
                : '0',
            'deducted_amount' => (string) $contract->installment_amount,
            'deduction_date' => now()->toDateString(),
            'deduction_type_id' => InstallmentDeductionType::Bank->value,
        ]);
    }

    protected function loadContractPreview(int $contractId): void
    {
        try {
            $resolved = app(DeductionBatchService::class)->resolveContract(
                $this->batch,
                (string) ($this->lineData['bank_account_number'] ?? ''),
                $contractId,
            );

            $this->applyResolvedContract($resolved['contract'], $resolved['entry_type']);
        } catch (ValidationException $exception) {
            $this->statusMessage = collect($exception->errors())->flatten()->first();
            $this->statusColor = 'danger';
        }
    }

    /**
     * @return array<int, string>
     */
    protected function contractOptions(): array
    {
        $account = trim((string) ($this->lineData['bank_account_number'] ?? ''));

        if ($account === '') {
            return [];
        }

        $service = app(DeductionBatchService::class);
        $options = [];

        foreach ($service->activeContractsForAccount($this->batch, $account) as $contract) {
            $options[(int) $contract->getKey()] = sprintf(
                '%s %s %s',
                (string) $contract->id,
                (string) ($contract->customer?->name ?? ''),
                number_format((float) $contract->contract_total, 3, '.', ','),
            );
        }

        foreach ($service->archiveContractsForAccount($this->batch, $account) as $contract) {
            $id = (int) $contract->getKey();

            if (isset($options[$id])) {
                continue;
            }

            $options[$id] = sprintf(
                '[أرشيف] %s %s %s',
                (string) $contract->id,
                (string) ($contract->customer?->name ?? ''),
                number_format((float) $contract->contract_total, 3, '.', ','),
            );
        }

        return $options;
    }
}
