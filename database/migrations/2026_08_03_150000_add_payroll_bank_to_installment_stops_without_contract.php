<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use MigratesCompanyDatabases;

    public function up(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (! Schema::connection($connection)->hasTable('installment_stops_without_contract')) {
                return;
            }

            if (! Schema::connection($connection)->hasColumn('installment_stops_without_contract', 'payroll_bank_id')) {
                Schema::connection($connection)->table('installment_stops_without_contract', function (Blueprint $table): void {
                    $table->foreignId('payroll_bank_id')
                        ->nullable()
                        ->after('name')
                        ->constrained('payroll_banks')
                        ->noActionOnDelete();
                });
            }

            $this->normalizeExistingAccounts($connection);
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (! Schema::connection($connection)->hasTable('installment_stops_without_contract')) {
                return;
            }

            if (! Schema::connection($connection)->hasColumn('installment_stops_without_contract', 'payroll_bank_id')) {
                return;
            }

            Schema::connection($connection)->table('installment_stops_without_contract', function (Blueprint $table): void {
                $table->dropForeign(['payroll_bank_id']);
                $table->dropColumn('payroll_bank_id');
            });
        });
    }

    protected function normalizeExistingAccounts(string $connection): void
    {
        if (! Schema::connection($connection)->hasTable('payroll_banks')) {
            return;
        }

        /** @var Collection<int, object{id: int, account_number: string|null}> $payrollBanks */
        $payrollBanks = DB::connection($connection)
            ->table('payroll_banks')
            ->whereNotNull('account_number')
            ->where('account_number', '!=', '')
            ->orderByRaw('LEN(account_number) DESC')
            ->get(['id', 'account_number']);

        $rows = DB::connection($connection)
            ->table('installment_stops_without_contract')
            ->get(['id', 'account_number', 'payroll_bank_id']);

        foreach ($rows as $row) {
            $accountNumber = trim((string) ($row->account_number ?? ''));

            if ($accountNumber === '') {
                continue;
            }

            $payrollBankId = $row->payroll_bank_id ? (int) $row->payroll_bank_id : null;
            $normalizedAccount = $accountNumber;

            if (! $payrollBankId) {
                foreach ($payrollBanks as $bank) {
                    $prefix = trim((string) $bank->account_number);

                    if ($prefix === '' || ! str_starts_with($accountNumber, $prefix)) {
                        continue;
                    }

                    $payrollBankId = (int) $bank->id;
                    $normalizedAccount = substr($accountNumber, strlen($prefix));

                    break;
                }
            }

            if (! $payrollBankId) {
                $contract = DB::connection($connection)
                    ->table('installment_contracts')
                    ->where('bank_account_number', $accountNumber)
                    ->whereNotNull('payroll_bank_id')
                    ->orderBy('id')
                    ->first(['payroll_bank_id']);

                $payrollBankId = $contract?->payroll_bank_id ? (int) $contract->payroll_bank_id : null;
            }

            if ($normalizedAccount === $accountNumber && ! $payrollBankId) {
                continue;
            }

            DB::connection($connection)
                ->table('installment_stops_without_contract')
                ->where('id', $row->id)
                ->update([
                    'payroll_bank_id' => $payrollBankId,
                    'account_number' => $normalizedAccount !== '' ? $normalizedAccount : $accountNumber,
                ]);
        }
    }
};
