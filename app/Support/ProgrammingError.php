<?php

namespace App\Support;

use Filament\Notifications\Notification;
use Throwable;

final class ProgrammingError
{
    public static function notify(Throwable $exception): void
    {
        info($exception->getMessage());

        Notification::make()
            ->title('حدث خطأ !! يرجي التواصل مع المبرمج')
            ->danger()
            ->send();
    }
}
