<?php

namespace App\Support;

use App\Models\InstallmentCancelledContract;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentSurplus;
use App\Models\InstallmentSuspended;
use Illuminate\Database\Eloquent\Model;

class SystemOperationContext
{
    public function __construct(
        public readonly ?int $customerId = null,
        public readonly ?int $itemId = null,
    ) {}

    public static function make(?int $customerId = null, ?int $itemId = null): self
    {
        return new self($customerId, $itemId);
    }

    public static function customer(?int $customerId): self
    {
        return new self(customerId: $customerId);
    }

    public static function item(?int $itemId): self
    {
        return new self(itemId: $itemId);
    }

    public static function fromContract(?Model $contract): self
    {
        if ($contract instanceof InstallmentContract
            || $contract instanceof InstallmentContractArchive
            || $contract instanceof InstallmentCancelledContract) {
            return self::customer(
                filled($contract->customer_id) ? (int) $contract->customer_id : null,
            );
        }

        return new self;
    }

    public static function fromSurplus(InstallmentSurplus $surplus): self
    {
        $contract = $surplus->contractable;

        if ($contract instanceof InstallmentContract
            || $contract instanceof InstallmentContractArchive
            || $contract instanceof InstallmentCancelledContract) {
            return self::fromContract($contract);
        }

        return new self;
    }

    public static function fromSuspended(InstallmentSuspended $suspended): self
    {
        if ($suspended->installment_contract_id) {
            $connection = $suspended->getConnectionName();

            $cancelled = InstallmentCancelledContract::on($connection)
                ->find($suspended->installment_contract_id);

            if ($cancelled) {
                return self::fromContract($cancelled);
            }

            $active = InstallmentContract::on($connection)
                ->find($suspended->installment_contract_id);

            if ($active) {
                return self::fromContract($active);
            }
        }

        $source = $suspended->contractable;

        if ($source instanceof InstallmentContract
            || $source instanceof InstallmentContractArchive
            || $source instanceof InstallmentCancelledContract) {
            return self::fromContract($source);
        }

        if ($source instanceof InstallmentSurplus) {
            return self::fromSurplus($source);
        }

        return new self;
    }
}
