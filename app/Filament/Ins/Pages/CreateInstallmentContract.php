<?php

namespace App\Filament\Ins\Pages;

use App\Filament\Ins\Concerns\RecalculatesInstallmentAmount;
use App\Filament\Ins\Support\InstallmentContractFieldAttributes;
use App\Models\InstallmentBank;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\SalesInvoice;
use App\Models\Workplace;
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
use Illuminate\Validation\ValidationException;

class CreateInstallmentContract extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use RecalculatesInstallmentAmount;

    protected static ?string $navigationLabel = 'ادخال عقد';

    protected static ?string $slug = 'create-installment-contract';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-plus';

    protected string $view = 'filament.ins.pages.create-installment-contract';

    protected ?string $heading = '';

    public array $contractData = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->is_prog) {
            return true;
        }

        return $user->can('ادخال عقود') && CompanySettings::linkSalesToInstallments();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $defaults = $this->defaultFormState();

        if ($salesInvoiceId = request()->integer('sales_invoice_id')) {
            $invoice = SalesInvoice::query()->find($salesInvoiceId);

            if ($invoice && ! $invoice->hasInstallmentContract()) {
                $defaults = array_merge($defaults, $this->stateFromInvoice($invoice));
            }
        }

        $this->contractForm->fill($defaults);
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
                                        modifyQueryUsing: fn (Builder $query) => InstallmentContractService::eligibleSalesInvoicesQuery(),
                                    )
                                    ->getOptionLabelFromRecordUsing(
                                        fn (SalesInvoice $record): string => sprintf(
                                            '%s %s %s',
                                            (string) $record->id,
                                            (string) ($record->customer?->name ?? ''),
                                            number_format((float) $record->grand_total, 3, '.', ','),
                                        ),
                                    )
                                    ->suffixAction(
                                        Action::make('newInstallmentInvoice')
                                            ->icon('heroicon-m-plus')
                                            ->url(fn (): string => CreateInstallmentSalesInvoice::getUrl()),
                                    )
                                    ->afterStateUpdated(function (?int $state, Set $set): void {
                                        if (! $state) {
                                            return;
                                        }

                                        $invoice = SalesInvoice::query()->find($state);

                                        if (! $invoice) {
                                            return;
                                        }

                                        $set('customer_id', $invoice->customer_id);
                                        $set('invoice_balance', $invoice->balance);
                                        $set('contract_total', $invoice->balance);
                                        $set('id', InstallmentContractService::nextContractId());

                                        $previous = app(InstallmentContractService::class)
                                            ->previousCustomerContract((int) $invoice->customer_id);

                                        if ($previous) {
                                            $set('installment_bank_id', $previous->installment_bank_id);
                                            $set('payroll_bank_id', $previous->payroll_bank_id);
                                            $set('bank_account_number', $previous->bank_account_number);
                                        }
                                    })
                                    ->columnSpanFull(),
                                Hidden::make('customer_id'),
                                TextInput::make('invoice_balance')
                                    ->prefix('الاجمالي')
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
                            ])
                            ->columnSpan(2)
                            ->columns(4),
                        Section::make()
                            ->schema([
                                Select::make('installment_bank_id')
                                    ->prefix('المصرف')
                                    ->hiddenLabel()
                                    ->columnSpan(2)
                                    ->searchable()
                                    ->preload()
                                    ->relationship('installmentBank', 'name')
                                    ->createOptionForm([
                                        Section::make('ادخال مصرف')
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('اسم المصرف')
                                                    ->required()
                                                    ->maxLength(255),
                                                Select::make('payroll_bank_id')
                                                    ->label('المصرف التجميعي')
                                                    ->relationship('payrollBank', 'name')
                                                    ->searchable()
                                                    ->preload()
                                                    ->createOptionForm([
                                                        TextInput::make('name')
                                                            ->label('المصرف التجميعي')
                                                            ->required()
                                                            ->maxLength(255),
                                                        TextInput::make('account_number')
                                                            ->label('رقم الحساب'),
                                                    ])
                                                    ->required(),
                                            ]),
                                    ])
                                    ->afterStateUpdated(function (?int $state, Set $set): void {
                                        if (! $state) {
                                            return;
                                        }

                                        $payrollBankId = InstallmentBank::query()
                                            ->whereKey($state)
                                            ->value('payroll_bank_id');

                                        $set('payroll_bank_id', $payrollBankId);
                                    })
                                    ->required()
                                    ->id('installment_bank_id'),
                                Hidden::make('payroll_bank_id'),
                                TextInput::make('bank_account_number')
                                    ->prefix('رقم الحساب')
                                    ->hiddenLabel()
                                    ->required()
                                    ->columnSpan(2)
                                    ->id('bank_account_number'),
                                Select::make('workplace_id')
                                    ->prefix('مكان العمل')
                                    ->hiddenLabel()
                                    ->columnSpan(4)
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->relationship('workplace', 'name')
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('مكان العمل')
                                            ->required(),
                                    ]),
                                DatePicker::make('contract_start')
                                    ->label('تاريخ العقد')
                                    ->required()
                                    ->maxDate(now())
                                    ->columnSpan(2)
                                    ->id('contract_start'),
                                TextInput::make('contract_total')
                                    ->label('قيمة العقد')
                                    ->readOnly()
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
                                    ->id('installment_amount'),
                                TextInput::make('cheques_in')
                                    ->prefix('عدد الصكوك المستلمة')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->columnSpan(2)
                                    ->id('cheques_in'),
                                Hidden::make('cheques_out')
                                    ->default(0),
                                Textarea::make('notes')
                                    ->label('ملاحظات')
                                    ->columnSpanFull(),
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
                            ->columnSpan(2)
                            ->columns(4),
                    ])
                    ->columns(3),
            ]);
    }

    public function storeContract(): void
    {
        $this->contractForm->validate();

        try {
            app(InstallmentContractService::class)->create($this->contractData);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?? 'تعذر حفظ العقد')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('تم تخزين العقد بنجاح')
            ->success()
            ->send();

        $this->resetForm();
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

    protected function stateFromInvoice(SalesInvoice $invoice): array
    {
        $state = [
            'sales_invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'invoice_balance' => $invoice->balance,
            'contract_total' => $invoice->balance,
        ];

        $previous = app(InstallmentContractService::class)
            ->previousCustomerContract((int) $invoice->customer_id);

        if ($previous) {
            $state['installment_bank_id'] = $previous->installment_bank_id;
            $state['payroll_bank_id'] = $previous->payroll_bank_id;
            $state['bank_account_number'] = $previous->bank_account_number;
        }

        return $state;
    }
}
