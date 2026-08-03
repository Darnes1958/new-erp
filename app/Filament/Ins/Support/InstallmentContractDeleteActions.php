<?php

namespace App\Filament\Ins\Support;

use App\Models\InstallmentContract;
use App\Services\Installments\InstallmentContractService;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;

class InstallmentContractDeleteActions
{
    public static function canDeleteByPermission(): bool
    {
        return Auth::user()?->can('الغاء عقود') || Auth::user()?->is_prog;
    }

    public static function make(bool $iconButton = false, bool | Closure $visible = true, ?Closure $afterDelete = null): Action
    {
        $action = Action::make('deleteContract')
            ->label('حذف العقد')
            ->icon('heroicon-m-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalWidth(Width::Large)
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('danger')
            ->modalHeading('تنبيه: حذف عقد تقسيط')
            ->modalContent(fn () => view('filament.ins.modals.delete-installment-contract-warning'))
            ->modalSubmitActionLabel('تأكيد')
            ->modalCancelActionLabel('إلغاء')
            ->visible($visible)
            ->action(function (Action $action) use ($afterDelete): void {
                $record = $action->getRecord();

                if (! $record instanceof InstallmentContract) {
                    return;
                }

                app(InstallmentContractService::class)->cancel($record);

                if ($afterDelete) {
                    $afterDelete($record, $action->getLivewire());
                }
            });

        if ($iconButton) {
            $action->iconButton()->iconSize(IconSize::Small);
        }

        return $action;
    }
}
