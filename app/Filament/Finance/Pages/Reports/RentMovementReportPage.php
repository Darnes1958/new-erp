<?php

namespace App\Filament\Finance\Pages\Reports;

use App\Enums\RentTransactionType;
use App\Filament\Finance\Pages\Reports\Concerns\InteractsWithFinanceMovementReportExports;
use App\Filament\Finance\Support\FinanceNavigationGroup;
use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Models\BankAccount;
use App\Models\CashBox;
use App\Models\RentProfile;
use App\Models\RentTransaction;
use App\Services\Excel\FinanceExcelService;
use App\Services\Finance\FinanceMovementReportService;
use App\Services\Finance\RentTransactionService;
use App\Services\Pdf\FinanceMovementPdfService;
use App\Support\PdfDownload;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class RentMovementReportPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithFinanceMovementReportExports;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'حركة إيجار';

    protected static ?string $title = 'حركة إيجار';

    protected static ?string $slug = 'rent-movement-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = FinanceNavigationGroup::Rents;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.finance.pages.reports.movement-report';

    protected ?string $heading = '';

    public ?int $rentProfileId = null;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return \App\Support\Authz::can('تقارير ايجارات', 'إيجارات');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->filtersForm->fill([
            'rentProfileId' => $this->rentProfileId,
        ]);
    }

    protected function getForms(): array
    {
        return [
            'filtersForm',
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->schema([
                        Select::make('rentProfileId')
                            ->columnSpan(3)
                            ->hiddenLabel()
                            ->prefix('الاسم')
                            ->options(fn (): array => RentProfile::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->live(),
                        Placeholder::make('balance')
                            ->columnSpan(2)
                            ->label('الرصيد')
                            ->content(function (Get $get): HtmlString|string {
                                $profileId = $get('rentProfileId');

                                if (! filled($profileId)) {
                                    return '0';
                                }

                                $balance = (float) RentProfile::query()->whereKey($profileId)->value('balance');

                                if ($balance < 0) {
                                    return new HtmlString('<span class="text-danger-600">'.$balance.'</span>');
                                }

                                return new HtmlString('<span class="text-indigo-700">'.$balance.'</span>');
                            }),
                        Actions::make([
                            InstallmentListPrintActions::compactPrint('print', fn () => $this->downloadPdf()),
                            InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadExcel()),
                        ])
                            ->columnSpan(1)
                            ->extraAttributes(['class' => 'finance-compact-exports']),
                    ])
                    ->columns(6),
            ])
            ->extraAttributes(['class' => 'gap-y-2']);
    }

    public function updatedRentProfileId(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $service = app(FinanceMovementReportService::class);

        return $table
            ->query(fn (): Builder => $this->buildReportQuery())
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('transaction_type')
                    ->label('البيان')
                    ->formatStateUsing(fn (RentTransactionType $state): string => $state->getLabel())
                    ->color(fn (RentTransactionType $state): string => match ($state) {
                        RentTransactionType::Rent => 'success',
                        RentTransactionType::Withdrawal => 'danger',
                    }),
                TextColumn::make('payment_source')
                    ->label('دفعت من')
                    ->state(fn ($record): string => $service->paymentSourceLabel($record))
                    ->color(fn ($record): string => $record->bank_account_id ? 'info' : 'success'),
                TextColumn::make('period_month')
                    ->label('عن شهر')
                    ->formatStateUsing(fn (?string $state): string => $service->formatPeriodMonth($state)),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->numeric(3)
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('')
                            ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ','),
                    ),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40),
            ])
            ->recordActions([
                Action::make('editMovement')
                    ->label('تعديل')
                    ->icon('heroicon-m-pencil-square')
                    ->modalSubmitActionLabel('حفظ')
                    ->fillForm(function (RentTransaction $record): array {
                        return [
                            'transaction_date' => $record->transaction_date,
                            'period_month' => $record->period_month,
                            'amount' => $record->amount,
                            'notes' => $record->notes,
                            'pay_type' => $record->bank_account_id ? 2 : 1,
                            'bank_account_id' => $record->bank_account_id,
                            'cash_box_id' => $record->cash_box_id,
                        ];
                    })
                    ->schema(fn (RentTransaction $record): array => $this->movementEditSchema($record))
                    ->action(function (RentTransaction $record, array $data): void {
                        $payType = (int) ($data['pay_type'] ?? 1);
                        app(RentTransactionService::class)->updateTransaction($record, [
                            'transaction_date' => $data['transaction_date'],
                            'amount' => $data['amount'],
                            'notes' => $data['notes'] ?? null,
                            'period_month' => $data['period_month'] ?? $record->period_month,
                            'bank_account_id' => $payType === 2 ? ($data['bank_account_id'] ?? null) : null,
                            'cash_box_id' => $payType === 1 ? ($data['cash_box_id'] ?? null) : null,
                        ]);

                        Notification::make()->title('تم تعديل الحركة بنجاح')->success()->send();
                    }),
                Action::make('cancelMovement')
                    ->label('إلغاء')
                    ->icon('heroicon-m-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('إلغاء الحركة')
                    ->modalDescription('سيتم إلغاء هذه الحركة وإعادة احتساب رصيد الإيجار.')
                    ->action(function (RentTransaction $record): void {
                        app(RentTransactionService::class)->deleteTransaction($record);
                        Notification::make()->title('تم إلغاء الحركة بنجاح')->success()->send();
                    }),
            ])
            ->defaultSort(fn ($query) => $query->orderBy('transaction_date')->orderBy('id'))
            ->emptyStateHeading('لا توجد بيانات')
            ->paginated([10, 25, 50, 100])
            ->striped();
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    protected function movementEditSchema(RentTransaction $record): array
    {
        $schema = [
            DatePicker::make('transaction_date')
                ->label('التاريخ')
                ->required(),
        ];

        if ($record->transaction_type === RentTransactionType::Rent) {
            $schema[] = DatePicker::make('period_month')
                ->label('عن شهر')
                ->native(false)
                ->displayFormat('Y/m')
                ->format('Y/m')
                ->closeOnDateSelection();
        }

        if ($record->transaction_type === RentTransactionType::Withdrawal) {
            $schema[] = Radio::make('pay_type')
                ->label('طريقة الدفع')
                ->options([1 => 'نقداً', 2 => 'مصرفي'])
                ->live();
            $schema[] = Select::make('bank_account_id')
                ->label('المصرف')
                ->options(fn (): array => BankAccount::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->required()
                ->visible(fn (Get $get): bool => (int) $get('pay_type') === 2);
            $schema[] = Select::make('cash_box_id')
                ->label('الخزينة')
                ->options(fn (): array => CashBox::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->required()
                ->visible(fn (Get $get): bool => (int) $get('pay_type') === 1);
        }

        $schema[] = TextInput::make('amount')
            ->label('المبلغ')
            ->numeric()
            ->required();
        $schema[] = TextInput::make('notes')
            ->label('ملاحظات');

        return $schema;
    }

    protected function buildReportQuery(): Builder
    {
        return app(FinanceMovementReportService::class)->rentTransactionsQuery($this->rentProfileId);
    }

    protected function validateReportFilters(): bool
    {
        if (! filled($this->rentProfileId)) {
            Notification::make()
                ->title('اختر الإيجار أولاً')
                ->warning()
                ->send();

            return false;
        }

        return true;
    }

    protected function exportSortColumn(): string
    {
        return 'transaction_date';
    }

    protected function downloadPdf(): mixed
    {
        $rows = $this->exportRows('لا توجد بيانات للطباعة');

        if ($rows === null) {
            return null;
        }

        $profile = app(FinanceMovementReportService::class)->resolveRentProfile($this->rentProfileId);

        return PdfDownload::streamed(
            app(FinanceMovementPdfService::class)->rentMovement(
                $rows,
                $profile?->name ?? '',
                (float) ($profile?->balance ?? 0),
            ),
        );
    }

    protected function downloadExcel(): mixed
    {
        $rows = $this->exportRows();

        if ($rows === null) {
            return null;
        }

        $profile = app(FinanceMovementReportService::class)->resolveRentProfile($this->rentProfileId);

        return app(FinanceExcelService::class)->movementReport(
            rows: $rows,
            reportTitle: 'كشف حساب إيجار : '.($profile?->name ?? ''),
            kind: 'transaction',
            balance: (float) ($profile?->balance ?? 0),
            filename: 'rent-movement.xlsx',
        );
    }
}
