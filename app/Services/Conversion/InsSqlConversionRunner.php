<?php

namespace App\Services\Conversion;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Runs INS bulk-conversion SQL scripts with automatic legacy/target name substitution.
 *
 * Scripts live in database/conversion/ins/ and were authored for BenTaher;
 * BenTaher / BenTaher_erp / new_erp are replaced at runtime.
 */
class InsSqlConversionRunner
{
    /** @var list<string> */
    protected array $scriptOrder = [
        '02b_warehouse_stocks.sql',
        '03_fix_bentaher_mappings.sql',
        '05_default_cash_accounts.sql',
        '02c_purchases.sql',
        '04_customer_receipts.sql',
        '06_supplier_payments.sql',
        '08_deductions_cleanup.sql',
        '09_installment_surplus_returns.sql',
        '10_deduction_batches.sql',
        '11_installment_cheques.sql',
    ];

    public function __construct(
        protected string $legacyConnection,
        protected string $targetConnection,
    ) {}

    /**
     * @param  callable(string): void|null  $log
     */
    public function runAll(?callable $log = null): void
    {
        foreach ($this->scriptOrder as $script) {
            $this->runScript($script, $log);
        }
    }

    /**
     * @param  callable(string): void|null  $log
     */
    public function runScript(string $filename, ?callable $log = null): void
    {
        $path = database_path('conversion/ins/'.$filename);

        if (! is_file($path)) {
            throw new RuntimeException("Conversion script not found: {$path}");
        }

        $log ??= static fn (string $message): null => null;
        $log("SQL: {$filename}");

        $sql = $this->prepareSql((string) file_get_contents($path));

        foreach ($this->splitBatches($sql) as $batch) {
            $batch = trim($batch);

            if ($batch === '' || $this->isCommentOnlyBatch($batch)) {
                continue;
            }

            DB::connection($this->targetConnection)->unprepared($batch);
        }
    }

    protected function prepareSql(string $sql): string
    {
        $legacyDb = $this->quoteIdentifier(DB::connection($this->legacyConnection)->getDatabaseName());
        $targetDb = $this->quoteIdentifier(DB::connection($this->targetConnection)->getDatabaseName());
        $centralDb = $this->quoteIdentifier(
            DB::connection(config('erp.central_connection', config('database.default')))->getDatabaseName()
        );

        $legacyCompany = str_replace("'", "''", $this->legacyConnection);
        $targetCompany = str_replace("'", "''", $this->targetConnection);

        $pairs = [
            'BenTaher_erp' => $targetDb,
            'BenTaher' => $legacyDb,
            'new_erp' => $centralDb,
            "N'BenTaher_erp'" => "N'{$targetCompany}'",
            "N'BenTaher'" => "N'{$legacyCompany}'",
        ];

        $sql = str_replace(array_keys($pairs), array_values($pairs), $sql);

        return $this->adaptMainArcArchiveIdExpressions($sql);
    }

    protected function adaptMainArcArchiveIdExpressions(string $sql): string
    {
        if (InsMainArc::hasLegacyIdColumn($this->legacyConnection)) {
            return $sql;
        }

        $minExpr = InsMainArc::sqlMinArchiveIdExpression($this->legacyConnection, 'ma');
        $minBare = InsMainArc::sqlMinArchiveIdExpression($this->legacyConnection, 'MainArc');

        $replacements = [
            'MIN(CAST(ma.id AS BIGINT))' => $minExpr,
            'SELECT no, MIN(id) AS id' => 'SELECT no, '.$minBare.' AS id',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $sql);
    }

    /**
     * @return list<string>
     */
    protected function splitBatches(string $sql): array
    {
        $parts = preg_split('/^\s*GO\s*$/mi', $sql) ?: [];

        return array_values(array_filter($parts, fn (string $part): bool => trim($part) !== ''));
    }

    protected function isCommentOnlyBatch(string $batch): bool
    {
        $lines = preg_split('/\R/', $batch) ?: [];
        $meaningful = array_filter($lines, function (string $line): bool {
            $trimmed = trim($line);

            return $trimmed !== ''
                && ! str_starts_with($trimmed, '/*')
                && ! str_starts_with($trimmed, '*')
                && ! str_starts_with($trimmed, '--');
        });

        return $meaningful === [];
    }

    protected function quoteIdentifier(string $name): string
    {
        return str_replace(']', ']]', $name);
    }
}
