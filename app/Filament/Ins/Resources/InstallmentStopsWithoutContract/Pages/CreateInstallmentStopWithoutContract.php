<?php

namespace App\Filament\Ins\Resources\InstallmentStopsWithoutContract\Pages;

use App\Filament\Ins\Resources\InstallmentStopsWithoutContract\InstallmentStopWithoutContractResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateInstallmentStopWithoutContract extends CreateRecord
{
    protected static string $resource = InstallmentStopWithoutContractResource::class;

    protected static bool $canCreateAnother = false;

    public function mount(): void
    {
        parent::mount();

        $this->restorePreviousPayrollBank();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $payrollBankId = $this->form->getRawState()['payroll_bank_id'] ?? null;

        if (filled($payrollBankId)) {
            session()->flash('installment_stop_without_contract.payroll_bank_id', $payrollBankId);
        }
    }

    protected function restorePreviousPayrollBank(): void
    {
        $payrollBankId = session()->pull('installment_stop_without_contract.payroll_bank_id');

        if (blank($payrollBankId)) {
            return;
        }

        $this->form->fill([
            'payroll_bank_id' => $payrollBankId,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('create');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تم إضافة الإيقاف';
    }
}
