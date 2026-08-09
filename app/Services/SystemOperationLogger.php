<?php

namespace App\Services;

use App\Enums\SystemOperationAction;
use App\Models\SystemOperationLog;
use App\Support\SystemOperationContext;
use Illuminate\Support\Facades\Auth;

class SystemOperationLogger
{
    public static function log(
        string $operation,
        SystemOperationAction|string $action,
        int|string $recordId,
        ?int $userId = null,
        ?string $connection = null,
        ?SystemOperationContext $context = null,
    ): SystemOperationLog {
        $actionEnum = $action instanceof SystemOperationAction
            ? $action
            : SystemOperationAction::from($action);

        $connection ??= Auth::user()?->company;
        $context ??= new SystemOperationContext;

        return SystemOperationLog::on(is_string($connection) ? $connection : null)->create([
            'operation' => $operation,
            'action' => $actionEnum,
            'record_id' => (int) $recordId,
            'customer_id' => $context->customerId,
            'item_id' => $context->itemId,
            'user_id' => $userId ?? Auth::id(),
            'created_at' => now(),
        ]);
    }

    public static function updated(
        string $operation,
        int|string $recordId,
        ?SystemOperationContext $context = null,
        ?int $userId = null,
    ): SystemOperationLog {
        return self::log($operation, SystemOperationAction::Update, $recordId, $userId, null, $context);
    }

    public static function cancelled(
        string $operation,
        int|string $recordId,
        ?SystemOperationContext $context = null,
        ?int $userId = null,
    ): SystemOperationLog {
        return self::log($operation, SystemOperationAction::Cancel, $recordId, $userId, null, $context);
    }
}
