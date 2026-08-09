<?php

namespace App\Services\Installments;

use App\Filament\Ins\Resources\InstallmentCancelledContracts\InstallmentCancelledContractResource;
use App\Filament\Ins\Resources\InstallmentContractArchives\InstallmentContractArchiveResource;
use App\Filament\Ins\Resources\InstallmentContracts\InstallmentContractResource;
use App\Models\InstallmentCancelledContract;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use Illuminate\Support\Collection;

class CustomerInstallmentContractsService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rowsForCustomer(int $customerId, string $tab): Collection
    {
        return match ($tab) {
            'archive' => $this->mapArchives(
                InstallmentContractArchive::query()
                    ->with('installmentBank')
                    ->where('customer_id', $customerId)
                    ->orderByDesc('archived_at')
                    ->orderByDesc('id')
                    ->get(),
            ),
            'cancelled' => $this->mapCancelled(
                InstallmentCancelledContract::query()
                    ->with('installmentBank')
                    ->where('customer_id', $customerId)
                    ->orderByDesc('cancelled_at')
                    ->orderByDesc('id')
                    ->get(),
            ),
            'all' => $this->mapActive(
                InstallmentContract::query()
                    ->with('installmentBank')
                    ->where('customer_id', $customerId)
                    ->orderByDesc('contract_start')
                    ->orderByDesc('id')
                    ->get(),
            )->concat($this->mapArchives(
                InstallmentContractArchive::query()
                    ->with('installmentBank')
                    ->where('customer_id', $customerId)
                    ->orderByDesc('archived_at')
                    ->orderByDesc('id')
                    ->get(),
            ))->concat($this->mapCancelled(
                InstallmentCancelledContract::query()
                    ->with('installmentBank')
                    ->where('customer_id', $customerId)
                    ->orderByDesc('cancelled_at')
                    ->orderByDesc('id')
                    ->get(),
            ))->sortByDesc('sort_date')->values(),
            default => $this->mapActive(
                InstallmentContract::query()
                    ->with('installmentBank')
                    ->where('customer_id', $customerId)
                    ->orderByDesc('contract_start')
                    ->orderByDesc('id')
                    ->get(),
            ),
        };
    }

    /**
     * @param  Collection<int, InstallmentContract>  $contracts
     * @return Collection<int, array<string, mixed>>
     */
    protected function mapActive(Collection $contracts): Collection
    {
        return $contracts->map(fn (InstallmentContract $contract): array => [
            'key' => 'active-'.$contract->id,
            'id' => $contract->id,
            'status' => 'active',
            'status_label' => 'قائم',
            'bank_name' => $contract->installmentBank?->name,
            'bank_account_number' => $contract->bank_account_number,
            'contract_start' => $contract->contract_start?->format('Y-m-d'),
            'contract_total' => (float) $contract->contract_total,
            'installment_amount' => (float) $contract->installment_amount,
            'total_paid' => (float) $contract->total_paid,
            'balance' => (float) $contract->balance,
            'status_date' => null,
            'sort_date' => $contract->contract_start?->format('Y-m-d') ?? '',
            'view_url' => InstallmentContractResource::getUrl('view', ['record' => $contract]),
        ]);
    }

    /**
     * @param  Collection<int, InstallmentContractArchive>  $archives
     * @return Collection<int, array<string, mixed>>
     */
    protected function mapArchives(Collection $archives): Collection
    {
        return $archives->map(fn (InstallmentContractArchive $archive): array => [
            'key' => 'archive-'.$archive->id,
            'id' => $archive->id,
            'status' => 'archive',
            'status_label' => 'أرشيف',
            'bank_name' => $archive->installmentBank?->name,
            'bank_account_number' => $archive->bank_account_number,
            'contract_start' => $archive->contract_start?->format('Y-m-d'),
            'contract_total' => (float) $archive->contract_total,
            'installment_amount' => (float) $archive->installment_amount,
            'total_paid' => (float) $archive->total_paid,
            'balance' => (float) $archive->balance,
            'status_date' => $archive->archived_at?->format('Y-m-d'),
            'sort_date' => $archive->archived_at?->format('Y-m-d') ?? '',
            'view_url' => InstallmentContractArchiveResource::getUrl('view', ['record' => $archive]),
        ]);
    }

    /**
     * @param  Collection<int, InstallmentCancelledContract>  $contracts
     * @return Collection<int, array<string, mixed>>
     */
    protected function mapCancelled(Collection $contracts): Collection
    {
        return $contracts->map(fn (InstallmentCancelledContract $contract): array => [
            'key' => 'cancelled-'.$contract->id,
            'id' => $contract->id,
            'status' => 'cancelled',
            'status_label' => 'ملغى',
            'bank_name' => $contract->installmentBank?->name,
            'bank_account_number' => $contract->bank_account_number,
            'contract_start' => $contract->contract_start?->format('Y-m-d'),
            'contract_total' => (float) $contract->contract_total,
            'installment_amount' => (float) $contract->installment_amount,
            'total_paid' => (float) $contract->total_paid,
            'balance' => (float) $contract->balance,
            'status_date' => $contract->cancelled_at?->format('Y-m-d'),
            'sort_date' => $contract->cancelled_at?->format('Y-m-d') ?? '',
            'view_url' => InstallmentCancelledContractResource::getUrl('view', ['record' => $contract]),
        ]);
    }
}
