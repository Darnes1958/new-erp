<?php

namespace App\Filament\Ins\Pages;

use App\Filament\Ins\Concerns\RecalculatesInstallmentAmount;
use App\Filament\Ins\Support\InstallmentContractFieldAttributes;
use App\Filament\Ins\Resources\InstallmentContracts\InstallmentContractResource;
use App\Models\Customer;
use App\Models\InstallmentBank;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\SalesInvoice;
use App\Services\Installments\InstallmentContractService;
use App\Support\CompanySettings;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
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

class EditInstallmentContract extends Page implements HasSchemas
{
    use InteractsWithRecord;
    use InteractsWithSchemas;
    use RecalculatesInstallmentAmount;

    protected static string $resource = InstallmentContractResource::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected string $view = 'filament.ins.pages.create-installment-contract';

    protected ?string $heading = '';

    public array $contractData = [];

    public static function canAccess(array $parameters = []): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->is_prog) {
            return true;
        }

        return $user->can('تعديل عقود') && CompanySettings::linkSalesToInstallments();
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->loadMissing(['customer', 'salesInvoice']);

        $invoice = $this->record->salesInvoice;

        $this->contractForm->fill([
            ...$this->record->toArray(),
            'customer_name' => $this->record->customer?->name,
            'invoice_grand_total' => $invoice?->grand_total,
            'invoice_amount_paid' => $invoice?->amount_paid,
            'invoice_balance' => $invoice?->balance,
            'contract_total' => $invoice?->balance ?? $this->record->contract_total,
        ]);
    }

    public function contractForm(Schema $schema): Schema
    {
        $currentInvoiceId = (int) ($this->record->sales_invoice_id ?? 0);

        return $schema
            ->model(InstallmentContract::class)
            ->statePath('contractData')
            ->components([
                Grid::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                Select::make('sales_invoice_id')
                                    ->label('فاتورة المبيعات')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->relationship(
                                        name: 'salesInvoice',
                                        titleAttribute: 'id',
                                        modifyQueryUsing: fn (Builder $query) => InstallmentContractService::eligibleSalesInvoicesQueryForEdit($currentInvoiceId),
                                    )
                                    ->getOptionLabelFromRecordUsing(
                                        fn (SalesInvoice $record): string => sprintf(
                                            '%s %s %s',
                                            (string) $record->id,
                                            (string) ($record->customer?->name ?? ''),
                                            number_format((float) $record->grand_total, 3, '.', ','),
                                        ),
                                    )
                                    ->columnSpan(2)
                                    ->afterStateUpdated(function (?int $state, Set $set): void {
                                        if (! $state) {
                                            return;
                                        }

                                        $invoice = SalesInvoice::query()->find($state);

                                        if (! $invoice) {
                                            return;
                                        }

                                        $set('customer_id', $invoice->customer_id);
                                        $set('customer_name', $invoice->customer?->name);
                                        $set('invoice_grand_total', $invoice->grand_total);
                                        $set('invoice_amount_paid', $invoice->amount_paid);
                                        $set('invoice_balance', $invoice->balance);
                                        $set('contract_total', $invoice->balance);
                                    }),
                                TextInput::make('customer_name')
                                    ->label('الزبون')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(3),
                                Hidden::make('customer_id'),
                                TextInput::make('invoice_grand_total')
                                    ->label('الاجمالي')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('invoice_amount_paid')
                                    ->label('المدفوع')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('invoice_balance')
                                    ->label('الباقي')
                                    ->disabled()
                                    ->dehydrated(false),
                            ])
                            ->columns(5),
                        Section::make()
                            ->schema([
                                TextInput::make('id')
                                    ->label('رقم العقد')
                                    ->required()
                                    ->numeric()
                                    ->unique(ignorable: $this->record)
                                    ->unique(table: InstallmentContractArchive::class, column: 'id')
                                    ->id('contract_id'),
                                Select::make('installment_bank_id')
                                    ->label('المصرف')
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
                                    ->label('رقم الحساب')
                                    ->required()
                                    ->id('bank_account_number'),
                                Select::make('workplace_id')
                                    ->label('مكان العمل')
                                    ->searchable()
                                    ->preload()
                                    ->relationship('workplace', 'name')
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('مكان العمل')
                                            ->required(),
                                    ])
                                    ->required(),
                                DatePicker::make('contract_start')
                                    ->label('تاريخ العقد')
                                    ->required()
                                    ->maxDate(now())
                                    ->id('contract_start'),
                                TextInput::make('contract_total')
                                    ->label('قيمة العقد')
                                    ->readOnly()
                                    ->id('contract_total'),
                                TextInput::make('installment_count')
                                    ->label('عدد الأقساط')
                                    ->numeric()
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                        static::syncInstallmentAmount($get, $set, $state);
                                    })
                                    ->extraInputAttributes(InstallmentContractFieldAttributes::installmentCountEnterKey())
                                    ->required()
                                    ->id('installment_count'),
                                TextInput::make('installment_amount')
                                    ->label('القسط')
                                    ->numeric()
                                    ->required()
                                    ->id('installment_amount'),
                                TextInput::make('cheques_in')
                                    ->label('عدد الصكوك المستلمة')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->id('cheques_in'),
                                TextInput::make('cheques_out')
                                    ->label('عدد الصكوك المسلمة')
                                    ->numeric()
                                    ->readOnly(),
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
                                        ->url(fn (): string => InstallmentContractResource::getUrl('index')),
                                ])->columnSpanFull(),
                            ])
                            ->columns(4),
                    ])
                    ->columns(3),
            ]);
    }

    public function storeContract(): void
    {
        $this->contractForm->validate();

        try {
            app(InstallmentContractService::class)->updateLinked($this->record, $this->contractData);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?? 'تعذر حفظ العقد')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('تم تخزين البيانات بنجاح')
            ->success()
            ->send();

        $this->redirect(InstallmentContractResource::getUrl('index'));
    }
}
