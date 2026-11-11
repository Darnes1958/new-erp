<?php

namespace App\Filament\Ins\Support;

use App\Models\InstallmentContract;
use App\Services\Installments\InstallmentContractService;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

class InstallmentContractCancelAfterActions
{
    public const WARNING = 'cancelAfterContract';

    public static function canRun(): bool
    {
        return \App\Support\Authz::can('الغاء عقود');
    }

    /**
     * @return array<int, Action>
     */
    public static function make(bool $iconButton = false, bool | Closure $visible = true, ?Closure $after = null): array
    {
        $action = Action::make(self::WARNING)
            ->label('إلغاء بعد التعاقد')
            ->icon('heroicon-m-x-circle')
            ->color('warning')
            ->requiresConfirmation()
            ->modalWidth(Width::Large)
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalHeading('تنبيه: إلغاء عقد بعد التعاقد')
            ->modalContent(fn () => view('filament.ins.modals.cancel-after-contract-warning'))
            ->modalSubmitActionLabel('تأكيد الإلغاء')
            ->modalCancelActionLabel('تراجع')
            ->visible($visible)
            ->action(function (Action $action) use ($after): void {
                $record = $action->getRecord();

                if (! $record instanceof InstallmentContract) {
                    return;
                }

                try {
                    app(InstallmentContractService::class)->cancelAfterContract($record);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title(collect($exception->errors())->flatten()->first() ?: 'تعذر إلغاء العقد')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('تم نقل العقد إلى الملغية بعد التعاقد')
                    ->success()
                    ->send();

                if ($after) {
                    $after($record, $action->getLivewire());
                }
            });

        if ($iconButton) {
            $action->iconButton()->iconSize(IconSize::Small);
        }

        return [$action];
    }
}
