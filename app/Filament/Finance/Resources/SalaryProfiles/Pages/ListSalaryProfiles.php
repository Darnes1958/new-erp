<?php

namespace App\Filament\Finance\Resources\SalaryProfiles\Pages;

use App\Enums\SalaryTransactionType;
use App\Filament\Finance\Resources\SalaryProfiles\SalaryProfileResource;
use App\Models\SalaryProfile;
use App\Models\SalaryTransaction;
use App\Models\Warehouse;
use App\Models\BankAccount;
use App\Models\CashBox;
use App\Services\Finance\SalaryTransactionService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;

class ListSalaryProfiles extends ListRecords
{
    protected static string $resource = SalaryProfileResource::class;

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('الكل')->badge(SalaryProfile::query()->count()),
            'admin' => Tab::make('الإدارة')
                ->modifyQueryUsing(fn ($query) => $query->whereNull('warehouse_id'))
                ->badge(SalaryProfile::query()->whereNull('warehouse_id')->count()),
        ];

        foreach (Warehouse::query()->orderBy('id')->withCount('salaryProfiles')->get() as $warehouse) {
            $tabs['warehouse_'.$warehouse->id] = Tab::make($warehouse->name)
                ->badge($warehouse->salary_profiles_count)
                ->modifyQueryUsing(fn ($query) => $query->where('warehouse_id', $warehouse->id));
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('إضافة مرتب جديد'),
            Action::make('postMonthlySalaries')
                ->label('إدراج مرتبات')
                ->color('success')
                ->modalSubmitActionLabel('إدراج')
                ->schema([
                    DatePicker::make('period_month')
                        ->label('عن شهر')
                        ->required()
                        ->native(false)
                        ->displayFormat('Y/m')
                        ->format('Y/m')
                        ->closeOnDateSelection(),
                    DatePicker::make('transaction_date')
                        ->label('التاريخ')
                        ->required()
                        ->default(now()),
                ])
                ->action(function (array $data): void {
                    $posted = app(SalaryTransactionService::class)->postMonthlySalaries(
                        $data['period_month'],
                        $data['transaction_date'],
                    );

                    if ($posted) {
                        Notification::make()->title('تم إدراج المرتب بنجاح')->success()->send();

                        return;
                    }

                    Notification::make()
                        ->title('سبق إدراج هذا المرتب')
                        ->body('يرجى مراجعة المرتبات المدخلة سابقاً')
                        ->danger()
                        ->send();
                }),
            Action::make('cancelMonthlySalaries')
                ->label('إلغاء ادراج مرتب')
                ->color('danger')
                ->modalHeading('إلغاء ادراج مرتب')
                ->modalDescription('سيتم إلغاء إدراج المرتب لكل الموظفين عن الشهر المختار، ثم إعادة احتساب الأرصدة. السحب والإضافة والخصم لا تُمس.')
                ->modalSubmitActionLabel('تأكيد الإلغاء')
                ->schema([
                    Select::make('period_month')
                        ->label('عن شهر')
                        ->options(fn (): array => SalaryTransaction::query()
                            ->where('transaction_type', SalaryTransactionType::Salary->value)
                            ->where('period_month', '!=', '0')
                            ->distinct()
                            ->orderByDesc('period_month')
                            ->pluck('period_month', 'period_month')
                            ->all())
                        ->required()
                        ->helperText('يُلغى الإدراج الجماعي لهذا الشهر بالكامل.'),
                ])
                ->action(function (array $data): void {
                    $deleted = app(SalaryTransactionService::class)->cancelMonthlySalaries($data['period_month']);

                    if ($deleted < 1) {
                        Notification::make()->title('لا توجد مرتبات مدرجة لهذا الشهر')->warning()->send();

                        return;
                    }

                    Notification::make()
                        ->title('تم إلغاء إدراج المرتبات')
                        ->body('أُلغيت '.$deleted.' حركة، وأُعيد احتساب الأرصدة.')
                        ->success()
                        ->send();
                }),
            $this->salaryMovementAction('withdraw', 'سحب', SalaryTransactionType::Withdrawal, 'heroicon-o-minus-circle', true),
            $this->salaryMovementAction('addition', 'اضافة', SalaryTransactionType::Addition, 'heroicon-o-plus-circle', false),
            $this->salaryMovementAction('deduction', 'خصم', SalaryTransactionType::Deduction, null, false, false),
            Action::make('deactivateSalary')
                ->label('إيقاف مرتب')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('إيقاف مرتب')
                ->modalDescription('سيتم إيقاف الموظف المحدد ولن يُدرج له مرتب في الأشهر التالية.')
                ->schema([
                    Select::make('salary_profile_id')
                        ->label('الاسم')
                        ->options(fn (): array => $this->getFilteredTableQuery()
                            ->where('is_active', true)
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $updated = SalaryProfile::query()
                        ->whereKey($data['salary_profile_id'])
                        ->update(['is_active' => false]);

                    if ($updated < 1) {
                        Notification::make()->title('تعذر إيقاف المرتب')->danger()->send();

                        return;
                    }

                    Notification::make()->title('تم إيقاف المرتب بنجاح')->success()->send();
                }),
            Action::make('activateSalary')
                ->label('تشغيل مرتب')
                ->color('success')
                ->schema([
                    Select::make('salary_profile_id')
                        ->label('الاسم')
                        ->options(fn (): array => $this->getFilteredTableQuery()
                            ->where('is_active', false)
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    SalaryProfile::query()
                        ->whereKey($data['salary_profile_id'])
                        ->update(['is_active' => true]);

                    Notification::make()->title('تم تشغيل المرتب بنجاح')->success()->send();
                }),
        ];
    }

    protected function salaryMovementAction(
        string $name,
        string $label,
        SalaryTransactionType $type,
        ?string $icon = null,
        bool $withPayment = false,
        bool $withDate = true,
    ): Action {
        $action = Action::make($name)
            ->label($label)
            ->color($type === SalaryTransactionType::Deduction ? 'danger' : 'success');

        if ($icon) {
            $action->icon($icon);
        }

        $schema = [
            Select::make('salary_profile_id')
                ->label('الاسم')
                ->options(fn (): array => $this->getFilteredTableQuery()->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->required(),
        ];

        if ($withPayment) {
            $schema[] = Radio::make('pay_type')
                ->label('طريقة الدفع')
                ->options([1 => 'نقداً', 2 => 'مصرفي'])
                ->default(1)
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

        if ($withDate) {
            $schema[] = DatePicker::make('transaction_date')
                ->label('التاريخ')
                ->required()
                ->default(now());
        }

        $schema[] = TextInput::make('amount')->label('المبلغ')->numeric()->required();
        $schema[] = TextInput::make('notes')->label('ملاحظات');

        return $action
            ->schema($schema)
            ->action(function (array $data) use ($type, $withPayment, $withDate): void {
                app(SalaryTransactionService::class)->recordTransaction([
                    'salary_profile_id' => $data['salary_profile_id'],
                    'transaction_date' => $withDate ? $data['transaction_date'] : now()->toDateString(),
                    'amount' => $data['amount'],
                    'notes' => $data['notes'] ?? null,
                    'bank_account_id' => $withPayment && (int) ($data['pay_type'] ?? 1) === 2 ? $data['bank_account_id'] : null,
                    'cash_box_id' => $withPayment && (int) ($data['pay_type'] ?? 1) === 1 ? $data['cash_box_id'] : null,
                ], $type);

                Notification::make()->title('تمت العملية بنجاح')->success()->send();
            });
    }
}
