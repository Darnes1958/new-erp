<?php

namespace App\Filament\Ins\Resources\ConversionReviewIssues\Pages;

use App\Filament\Ins\Resources\ConversionReviewIssues\ConversionReviewIssueResource;
use App\Models\ConversionReviewIssue;
use App\Models\InstallmentContract;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditConversionReviewIssue extends EditRecord
{
    protected static string $resource = ConversionReviewIssueResource::class;

    protected static ?string $title = 'تصحيح ملاحظة الكونفيرت';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;
        if ($record instanceof ConversionReviewIssue && $record->contract_id) {
            $contract = InstallmentContract::query()->find($record->contract_id);
            if ($contract) {
                $data['fix_installment_amount'] = $contract->installment_amount;
                $data['fix_installment_count'] = $contract->installment_count;
                $data['fix_contract_total'] = $contract->contract_total;
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['fix_installment_amount'], $data['fix_installment_count'], $data['fix_contract_total']);

        if (($data['status'] ?? null) !== 'open') {
            $data['resolved_by'] = Auth::id();
            $data['resolved_at'] = now();
        } else {
            $data['resolved_by'] = null;
            $data['resolved_at'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        if (! $record instanceof ConversionReviewIssue || ! $record->contract_id) {
            return;
        }

        $state = $this->form->getRawState();
        $contract = InstallmentContract::query()->find($record->contract_id);
        if (! $contract) {
            return;
        }

        $updates = [];
        if (isset($state['fix_installment_amount'])) {
            $updates['installment_amount'] = round((float) $state['fix_installment_amount'], 3);
        }
        if (isset($state['fix_installment_count'])) {
            $updates['installment_count'] = (int) $state['fix_installment_count'];
            $updates['installments_remaining'] = max(0, (int) $state['fix_installment_count']);
        }
        if (isset($state['fix_contract_total'])) {
            $updates['contract_total'] = round((float) $state['fix_contract_total'], 3);
            $updates['balance'] = round(((float) $state['fix_contract_total']) - (float) $contract->total_paid, 3);
        }

        if ($updates !== []) {
            $contract->forceFill($updates)->save();
        }
    }
}
