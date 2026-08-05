<?php

namespace App\Filament\Ins\Pages;

use App\Filament\Ins\Concerns\RecalculatesInstallmentAmount;
use App\Filament\Ins\Support\InstallmentContractFieldAttributes;
use App\Models\InstallmentBank;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\SalesInvoice;
use App\Models\Workplace;
use App\Services\Installments\InstallmentContractMergeService;
use App\Services\Installments\InstallmentContractService;
use App\Support\CompanySettings;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class MergeInstallmentContract extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use RecalculatesInstallmentAmount;

    protected static ?string $navigationLabel = 'ضم عقد';

    protected static ?string $slug = 'merge-installment-contract';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected string $view = 'filament.ins.pages.merge-installment-contract';

    protected ?string $heading = '';

    public array $contractData = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return ($user->is_prog || $user->can('ادخال عقود')) && CompanySettings::linkSalesToInstallments();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->contractForm->fill($this->defaultFormState());
    }

    public function contractForm(Schema $schema): Schema
    {
        return $schema
            ->model(InstallmentContract::class)
            ->statePath('contractData')
            ->components([
                Grid::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                Select::make('sales_invoice_id')
                                    ->hiddenLabel()
                                    ->prefix('الفاتورة')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->relationship(
                                        name: 'salesInvoice',
                                        titleAttribute: 'id',
                                        modifyQueryUsing: fn (Builder $query) => InstallmentContractService::eligibleMergeSalesInvoicesQuery(),
                                    )
                                    ->getOptionLabelFromRecordUsing(
                                        fn (SalesInvoice $record): string => sprintf(
                                            '%s %s %s',
                                            (string) $record->id,
                                            (string) ($record->customer?->name ?? ''),
                                            number_format((float) $record->balance, 3, '.', ','),
                                        ),
                                    )
                                    ->suffixAction(
                                        Action::make('newInstallmentInvoice')
                                            ->icon('heroicon-m-plus')
                                            ->url(fn (): string => CreateInstallmentSalesInvoice::getUrl()),
                                    )
                                    ->afterStateUpdated(function (?int $state, Set $set): void {
                                        $this->resetPreviousContractFields($set);

                                        if (! $state) {
                                            return;
                                        }

                                        $invoice = SalesInvoice::query()->find($state);

                                        if (! $invoice) {
                                            return;
                                        }

                                        $previous = app(InstallmentContractService::class)
                                            ->activeCustomerContract((int) $invoice->customer_id);

                                        if (! $previous) {
                                            Notification::make()
                                                ->title('لا يوجد عقد قائم لهذا الزبون')
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        $this->fillPreviousAndNewContractState($set, $invoice, $previous);
                                        $this->focusField('installment_count');
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                        Section::make(new HtmlString('<span class="text-danger-600">بيانات العقد السابق</span>'))
                            ->schema([
                                Hidden::make('previous_contract_id'),
                                TextInput::make('previous_contract_total')
                                    ->label('قيمة العقد')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('previous_installment_amount')
                                    ->label('القسط')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('previous_total_paid')
                                    ->label('المدفوع')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('previous_balance')
                                    ->label('المتبقي')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->extraInputAttributes(['class' => 'font-bold text-danger-600']),
                            ])
                            ->columns(4)
                            ->visible(fn (Get $get): bool => filled($get('previous_contract_id'))),
                        Section::make(new HtmlString('<span class="text-danger-600">بيانات العقد الجديد</span>'))
                            ->schema([
                                Hidden::make('customer_id'),
                                TextInput::make('invoice_balance')
                                    ->prefix('الفاتورة')
                                    ->hiddenLabel()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(2),
                                TextInput::make('id')
                                    ->prefix('رقم العقد')
                                    ->hiddenLabel()
                                    ->required()
                                    ->numeric()
                                    ->unique(ignoreRecord: true)
                                    ->unique(table: InstallmentContractArchive::class, column: 'id')
                                    ->columnSpan(2)
                                    ->id('contract_id'),
                                Select::make('installment_bank_id')
                                    ->prefix('المصرف')
                                    ->hiddenLabel()
                                    ->columnSpan(2)
                                    ->searchable()
                                    ->preload()
                                    ->relationship('installmentBank', 'name')
                                    ->afterStateUpdated(function (?int $state, Set $set): void {
                                        if (! $state) {
                                            return;
                                        }

                                        $payrollBankId = InstallmentBank::query()
                                            ->whereKey($state)
                                            ->value('payroll_bank_id');

                                        $set('payroll_bank_id', $payrollBankId);
                                    })
                                    ->required(),
                                Hidden::make('payroll_bank_id'),
                                TextInput::make('bank_account_number')
                                    ->prefix('رقم الحساب')
                                    ->hiddenLabel()
                                    ->required()
                                    ->columnSpan(2),
                                Select::make('workplace_id')
                                    ->prefix('مكان العمل')
                                    ->hiddenLabel()
                                    ->columnSpan(4)
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->relationship('workplace', 'name'),
                                DatePicker::make('contract_start')
                                    ->label('تاريخ العقد')
                                    ->required()
                                    ->maxDate(now())
                                    ->columnSpan(2),
                                TextInput::make('contract_total')
                                    ->label('قيمة العقد')
                                    ->readOnly()
                                    ->required()
                                    ->columnSpan(2)
                                    ->id('contract_total'),
                                TextInput::make('installment_count')
                                    ->prefix('عدد الأقساط')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                        static::syncInstallmentAmount($get, $set, $state);
                                    })
                                    ->extraInputAttributes(InstallmentContractFieldAttributes::installmentCountEnterKey())
                                    ->required()
                                    ->columnSpan(2)
                                    ->id('installment_count'),
                                TextInput::make('installment_amount')
                                    ->prefix('القسط')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->required()
                                    ->columnSpan(2)
                                    ->id('installment_amount')
                                    ->extraInputAttributes(InstallmentContractFieldAttributes::enterFocusField('cheques_in')),
                                TextInput::make('cheques_in')
                                    ->prefix('عدد الصكوك المستلمة')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->columnSpan(2)
                                    ->id('cheques_in')
                                    ->extraInputAttributes(InstallmentContractFieldAttributes::enterFocusField('notes')),
                                Hidden::make('cheques_out')
                                    ->default(0),
                                Textarea::make('notes')
                                    ->label('ملاحظات')
                                    ->columnSpanFull()
                                    ->id('notes')
                                    ->extraAttributes([
                                        'x-on:keydown.enter.prevent' => '$wire.storeContract()',
                                    ]),
                                Actions::make([
                                    Action::make('storeContract')
                                        ->label('تخزين')
                                        ->color('success')
                                        ->action(fn () => $this->storeContract()),
                                    Action::make('cancelContract')
                                        ->label('تجاهل')
                                        ->color('info')
                                        ->action(fn () => $this->resetForm()),
                                ])->columnSpanFull(),
                            ])
                            ->columns(4)
                            ->visible(fn (Get $get): bool => filled($get('previous_contract_id'))),
                    ])
                    ->columns(1),
            ]);
    }

    public function storeContract(): void
    {
        $this->contractForm->validate();

        try {
            app(InstallmentContractMergeService::class)->merge($this->contractData);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?? 'تعذر ضم العقد')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('تمت عملية ضم العقد بنجاح')
            ->success()
            ->send();

        $this->resetForm();
    }

    public function focusField(string $field): void
    {
        $this->dispatch('focus-field', field: $field);
    }

    public function resetForm(): void
    {
        $this->contractForm->fill($this->defaultFormState());
    }

    protected function defaultFormState(): array
    {
        return [
            'contract_start' => now()->toDateString(),
            'id' => InstallmentContractService::nextContractId(),
            'workplace_id' => Workplace::query()->min('id'),
            'cheques_in' => 0,
            'cheques_out' => 0,
        ];
    }

    protected function resetPreviousContractFields(Set $set): void
    {
        foreach ([
            'previous_contract_id',
            'previous_contract_total',
            'previous_installment_amount',
            'previous_total_paid',
            'previous_balance',
            'customer_id',
            'invoice_balance',
            'contract_total',
            'installment_bank_id',
            'payroll_bank_id',
            'bank_account_number',
            'workplace_id',
            'installment_count',
            'installment_amount',
        ] as $field) {
            $set($field, null);
        }
    }

    protected function fillPreviousAndNewContractState(Set $set, SalesInvoice $invoice, InstallmentContract $previous): void
    {
        $set('previous_contract_id', $previous->id);
        $set('previous_contract_total', number_format((float) $previous->contract_total, 3, '.', ''));
        $set('previous_installment_amount', number_format((float) $previous->installment_amount, 3, '.', ''));
        $set('previous_total_paid', number_format((float) $previous->total_paid, 3, '.', ''));
        $set('previous_balance', number_format((float) $previous->balance, 3, '.', ''));

        $set('customer_id', $previous->customer_id);
        $set('invoice_balance', number_format((float) $invoice->balance, 3, '.', ''));
        $set('contract_total', number_format((float) $invoice->balance + (float) $previous->balance, 3, '.', ''));
        $set('id', InstallmentContractService::nextContractId());
        $set('installment_bank_id', $previous->installment_bank_id);
        $set('payroll_bank_id', $previous->payroll_bank_id);
        $set('bank_account_number', $previous->bank_account_number);
        $set('workplace_id', $previous->workplace_id ?? Workplace::query()->min('id'));
    }
}
