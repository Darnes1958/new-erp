<?php

namespace App\Filament\Ins\Support;

use App\Models\InstallmentContract;
use App\Services\Installments\InstallmentContractService;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;

class InstallmentContractCancelAfterActions
{
    public const WARNING = 'cancelAfterContract';

    public const CONFIRM = 'cancelAfterContractConfirm';

    public static function canRun(): bool
    {
        return Auth::user()?->can('الغاء عقود') || Auth::user()?->is_prog;
    }

    public static function warningAction(bool $iconButton = false, bool | Closure $visible = true): Action
    {
        $action = Action::make(self::WARNING)
            ->label('إلغاء بعد التعاقد')
            ->icon('heroicon-m-x-circle')
            ->color('warning')
            ->modalWidth(Width::Large)
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalHeading('تنبيه: إلغاء عقد بعد التعاقد')
            ->modalContent(fn () => view('filament.ins.modals.cancel-after-contract-warning'))
            ->modalSubmitActionLabel('متابعة إلى التأكيد')
            ->modalCancelActionLabel('إلغاء')
            ->visible($visible);

        if ($iconButton) {
            $action->iconButton()->iconSize(IconSize::Small);
        }

        return $action;
    }

    public static function confirmAction(?Closure $after = null): Action
    {
        return Action::make(self::CONFIRM)
            ->hidden()
            ->requiresConfirmation()
            ->modalHeading('نقل العقد إلى الملغية')
            ->modalDescription('سينقل العقد مع خصوماتhe إلى ملف العقود الملغية بعد التعاقد.')
            ->modalSubmitActionLabel('تأكيد')
            ->modalCancelActionLabel('إلغاء')
            ->action(function (Action $action) use ($after): void {
                $record = $action->getRecord();

                if (! $record instanceof InstallmentContract) {
                    return;
                }

                app(InstallmentContractService::class)->cancelAfterContract($record);

                if ($after) {
                    $after($record, $action->getLivewire());
                }
            });
    }

    /**
     * @return array<int, Action>
     */
    public static function make(bool $iconButton = false, bool | Closure $visible = true, ?Closure $after = null): array
    {
        return [
            self::warningAction($iconButton, $visible)
                ->action(function (Action $action): void {
                    $livewire = $action->getLivewire();
                    $record = $action->getRecord();

                    $context = $action->getTable() && $record
                        ? ['table' => true, 'recordKey' => (string) $record->getKey()]
                        : [];

                    $livewire->replaceMountedAction(self::CONFIRM, [], $context);
                }),
            self::confirmAction($after),
        ];
    }
}
