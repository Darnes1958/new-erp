<?php

namespace App\Support;

use Filament\Notifications\Notification;
use Throwable;

final class ProgrammingError
{
    /** @var list<string> */
    private const USER_FACING_MESSAGES = [
        'الرصيد لا يسمح',
        'لا يمكن تعديل بند تم ترجيعه',
    ];

    public static function notify(Throwable $exception): void
    {
        info($exception->getMessage());

        $message = $exception->getMessage();

        if (in_array($message, self::USER_FACING_MESSAGES, true)) {
            Notification::make()
                ->title($message)
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('حدث خطأ !! يرجي التواصل مع المبرمج')
            ->danger()
            ->send();
    }
}
