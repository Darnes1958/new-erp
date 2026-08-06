<?php

namespace App\Filament\Finance\Resources\RentProfiles\Pages;

use App\Filament\Finance\Resources\RentProfiles\RentProfileResource;
use App\Models\BankAccount;
use App\Models\CashBox;
use App\Models\RentProfile;
use App\Services\Finance\RentTransactionService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;

class ListRentProfiles extends ListRecords
{
    protected static string $resource = RentProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('إضافة'),
            Action::make('postMonthlyRents')
                ->label('إدراج إيجار')
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
                    Select::make('rent_profile_id')
                        ->label('المكان أو اتركه فارغاً للكل')
                        ->options(fn (): array => RentProfile::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload(),
                    DatePicker::make('transaction_date')
                        ->label('التاريخ')
                        ->required()
                        ->default(now()),
                ])
                ->action(function (array $data): void {
                    $posted = app(RentTransactionService::class)->postMonthlyRents(
                        $data['period_month'],
                        $data['transaction_date'],
                        $data['rent_profile_id'] ?? null,
                    );

                    if ($posted) {
                        Notification::make()->title('تم إدراج الإيجار بنجاح')->success()->send();

                        return;
                    }

                    Notification::make()
                        ->title('سبق إدراج هذا الإيجار')
                        ->body('يرجى مراجعة الإيجارات المدخلة سابقاً')
                        ->danger()
                        ->send();
                }),
            Action::make('withdraw')
                ->label('سحب')
                ->color('success')
                ->icon('heroicon-o-minus-circle')
                ->schema([
                    Radio::make('pay_type')
                        ->label('طريقة الدفع')
                        ->options([1 => 'نقداً', 2 => 'مصرفي'])
                        ->default(1)
                        ->live(),
                    Select::make('rent_profile_id')
                        ->label('الاسم')
                        ->options(fn (): array => $this->getFilteredTableQuery()->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('bank_account_id')
                        ->label('المصرف')
                        ->options(fn (): array => BankAccount::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->visible(fn (Get $get): bool => (int) $get('pay_type') === 2),
                    Select::make('cash_box_id')
                        ->label('الخزينة')
                        ->options(fn (): array => CashBox::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->visible(fn (Get $get): bool => (int) $get('pay_type') === 1),
                    DatePicker::make('transaction_date')
                        ->label('التاريخ')
                        ->required()
                        ->default(now()),
                    TextInput::make('amount')
                        ->label('المبلغ')
                        ->numeric()
                        ->required(),
                    TextInput::make('notes')
                        ->label('ملاحظات'),
                ])
                ->action(function (array $data): void {
                    app(RentTransactionService::class)->recordWithdrawal([
                        'rent_profile_id' => $data['rent_profile_id'],
                        'transaction_date' => $data['transaction_date'],
                        'amount' => $data['amount'],
                        'notes' => $data['notes'] ?? null,
                        'bank_account_id' => (int) ($data['pay_type'] ?? 1) === 2 ? $data['bank_account_id'] : null,
                        'cash_box_id' => (int) ($data['pay_type'] ?? 1) === 1 ? $data['cash_box_id'] : null,
                    ]);

                    Notification::make()->title('تم عملية السحب بنجاح')->success()->send();
                }),
        ];
    }
}
