<?php

namespace App\Filament\Ins\Support;

use App\Models\InstallmentContract;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

final class InstallmentContractViewModalAction
{
    public static function make(string $name = 'view'): Action
    {
        return Action::make($name)
            ->label('عرض')
            ->icon('heroicon-o-eye')
            ->iconButton()
            ->color('gray')
            ->tooltip('عرض')
            ->modalHeading(fn (Model $record): string => 'عقد تقسيط رقم '.$record->getKey())
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->modalContent(fn (Model $record): View => view(
                'filament.ins.modals.installment-contract-view',
                ['contract' => static::contract($record)],
            ));
    }

    protected static function contract(Model $record): ?InstallmentContract
    {
        $contract = $record instanceof InstallmentContract
            ? $record
            : InstallmentContract::query()->find($record->getKey());

        return $contract?->loadMissing([
            'customer',
            'installmentBank',
            'workplace',
            'salesInvoice',
        ])->load([
            'deductions' => fn ($query) => $query->orderBy('sequence')->orderBy('id'),
        ]);
    }
}
