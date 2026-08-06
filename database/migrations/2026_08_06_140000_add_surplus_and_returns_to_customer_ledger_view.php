<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use MigratesCompanyDatabases;

    public function up(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (! Schema::connection($connection)->hasTable('customer_receipts')) {
                return;
            }

            DB::connection($connection)->statement('IF OBJECT_ID(N\'customer_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW customer_ledger_entries');

            DB::connection($connection)->statement($this->viewSql($connection));
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (! Schema::connection($connection)->hasTable('installment_deductions')) {
                return;
            }

            DB::connection($connection)->statement('IF OBJECT_ID(N\'customer_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW customer_ledger_entries');

            DB::connection($connection)->statement(<<<'SQL'
CREATE VIEW customer_ledger_entries AS
SELECT
    lines.customer_id,
    lines.customer_name,
    lines.rep_date,
    lines.source_id AS id,
    ROW_NUMBER() OVER (
        PARTITION BY lines.customer_id
        ORDER BY lines.rep_date, lines.source_order, lines.sort_id
    ) AS idd,
    lines.transaction_kind,
    lines.payment_method_name,
    lines.mden,
    lines.daen,
    lines.notes
FROM (
    SELECT
        si.customer_id,
        c.name AS customer_name,
        si.invoice_date AS rep_date,
        si.id AS source_id,
        CAST(7 AS int) AS transaction_kind,
        pm.name AS payment_method_name,
        CAST(si.grand_total AS decimal(14, 3)) AS mden,
        CAST(0 AS decimal(14, 3)) AS daen,
        si.notes,
        CAST(1 AS int) AS source_order,
        si.id AS sort_id
    FROM sales_invoices si
    INNER JOIN customers c ON c.id = si.customer_id
    LEFT JOIN payment_methods pm ON pm.id = si.payment_method_id
    WHERE si.customer_id IS NOT NULL

    UNION ALL

    SELECT
        cr.customer_id,
        c.name,
        cr.receipt_date,
        cr.id,
        cr.transaction_kind,
        pm.name,
        CASE WHEN cr.flow_direction = 1 THEN cr.amount ELSE CAST(0 AS decimal(14, 3)) END,
        CASE WHEN cr.flow_direction = 0 THEN cr.amount ELSE CAST(0 AS decimal(14, 3)) END,
        cr.notes,
        CAST(2 AS int),
        cr.id
    FROM customer_receipts cr
    INNER JOIN customers c ON c.id = cr.customer_id
    LEFT JOIN payment_methods pm ON pm.id = cr.payment_method_id

    UNION ALL

    SELECT
        si.customer_id,
        c.name,
        sr.return_date,
        sr.id,
        CAST(15 AS int),
        pm.name,
        CAST(0 AS decimal(14, 3)),
        sr.line_total,
        sr.notes,
        CAST(3 AS int),
        sr.id
    FROM sales_returns sr
    INNER JOIN sales_invoices si ON si.id = sr.sales_invoice_id
    INNER JOIN customers c ON c.id = si.customer_id
    LEFT JOIN payment_methods pm ON pm.id = si.payment_method_id

    UNION ALL

    SELECT
        ic.customer_id,
        c.name,
        id.deduction_date,
        id.id,
        CAST(18 AS int),
        CASE id.deduction_type_id
            WHEN 1 THEN N'نقداً'
            WHEN 2 THEN N'المصرف'
            WHEN 3 THEN N'صك'
            WHEN 4 THEN N'إلكتروني'
            ELSE N'—'
        END,
        CAST(0 AS decimal(14, 3)),
        id.deducted_amount,
        COALESCE(id.notes, CONCAT(N'عقد رقم ', CAST(ic.id AS nvarchar(20)))),
        CAST(4 AS int),
        id.id
    FROM installment_deductions id
    INNER JOIN installment_contracts ic ON ic.id = id.installment_contract_id
    INNER JOIN customers c ON c.id = ic.customer_id
    WHERE ic.customer_id IS NOT NULL
) AS lines
SQL);
        });
    }

    protected function viewSql(string $connection): string
    {
        $parts = [$this->coreLinesSql()];

        if (Schema::connection($connection)->hasTable('installment_deductions')) {
            $parts[] = $this->installmentDeductionsSql();
        }

        if (Schema::connection($connection)->hasTable('installment_surplus')) {
            $parts[] = $this->installmentSurplusSql();
        }

        if (Schema::connection($connection)->hasTable('installment_surplus_archives')) {
            $parts[] = $this->installmentSurplusArchivesSql();
        }

        if (Schema::connection($connection)->hasTable('installment_suspended')) {
            $parts[] = $this->installmentReturnsSql();
        }

        $lines = implode("\n\n    UNION ALL\n\n", $parts);

        return <<<SQL
CREATE VIEW customer_ledger_entries AS
SELECT
    lines.customer_id,
    lines.customer_name,
    lines.rep_date,
    lines.source_id AS id,
    ROW_NUMBER() OVER (
        PARTITION BY lines.customer_id
        ORDER BY lines.rep_date, lines.source_order, lines.sort_id
    ) AS idd,
    lines.transaction_kind,
    lines.payment_method_name,
    lines.mden,
    lines.daen,
    lines.notes
FROM (
    {$lines}
) AS lines
SQL;
    }

    protected function coreLinesSql(): string
    {
        return <<<'SQL'
SELECT
        si.customer_id,
        c.name AS customer_name,
        si.invoice_date AS rep_date,
        si.id AS source_id,
        CAST(7 AS int) AS transaction_kind,
        pm.name AS payment_method_name,
        CAST(si.grand_total AS decimal(14, 3)) AS mden,
        CAST(0 AS decimal(14, 3)) AS daen,
        si.notes,
        CAST(1 AS int) AS source_order,
        si.id AS sort_id
    FROM sales_invoices si
    INNER JOIN customers c ON c.id = si.customer_id
    LEFT JOIN payment_methods pm ON pm.id = si.payment_method_id
    WHERE si.customer_id IS NOT NULL

    UNION ALL

    SELECT
        cr.customer_id,
        c.name,
        cr.receipt_date,
        cr.id,
        cr.transaction_kind,
        pm.name,
        CASE WHEN cr.flow_direction = 1 THEN cr.amount ELSE CAST(0 AS decimal(14, 3)) END,
        CASE WHEN cr.flow_direction = 0 THEN cr.amount ELSE CAST(0 AS decimal(14, 3)) END,
        cr.notes,
        CAST(2 AS int),
        cr.id
    FROM customer_receipts cr
    INNER JOIN customers c ON c.id = cr.customer_id
    LEFT JOIN payment_methods pm ON pm.id = cr.payment_method_id

    UNION ALL

    SELECT
        si.customer_id,
        c.name,
        sr.return_date,
        sr.id,
        CAST(15 AS int),
        pm.name,
        CAST(0 AS decimal(14, 3)),
        sr.line_total,
        sr.notes,
        CAST(3 AS int),
        sr.id
    FROM sales_returns sr
    INNER JOIN sales_invoices si ON si.id = sr.sales_invoice_id
    INNER JOIN customers c ON c.id = si.customer_id
    LEFT JOIN payment_methods pm ON pm.id = si.payment_method_id
SQL;
    }

    protected function installmentDeductionsSql(): string
    {
        return <<<'SQL'
SELECT
        ic.customer_id,
        c.name,
        id.deduction_date,
        id.id,
        CAST(18 AS int),
        CASE id.deduction_type_id
            WHEN 1 THEN N'نقداً'
            WHEN 2 THEN N'المصرف'
            WHEN 3 THEN N'صك'
            WHEN 4 THEN N'إلكتروني'
            ELSE N'—'
        END,
        CAST(0 AS decimal(14, 3)),
        id.deducted_amount,
        COALESCE(id.notes, CONCAT(N'عقد رقم ', CAST(ic.id AS nvarchar(20)))),
        CAST(4 AS int),
        id.id
    FROM installment_deductions id
    INNER JOIN installment_contracts ic ON ic.id = id.installment_contract_id
    INNER JOIN customers c ON c.id = ic.customer_id
    WHERE ic.customer_id IS NOT NULL
SQL;
    }

    protected function installmentSurplusSql(): string
    {
        return <<<'SQL'
SELECT
        ic.customer_id,
        c.name,
        os.surplus_date,
        os.id,
        CAST(19 AS int),
        N'—',
        CAST(0 AS decimal(14, 3)),
        os.amount,
        CONCAT(N'فائض — عقد رقم ', CAST(ic.id AS nvarchar(20))),
        CAST(5 AS int),
        os.id
    FROM installment_surplus os
    INNER JOIN installment_contracts ic
        ON os.contractable_type = N'installment_contract'
        AND os.contractable_id = ic.id
    INNER JOIN customers c ON c.id = ic.customer_id
    WHERE ic.customer_id IS NOT NULL

    UNION ALL

    SELECT
        ica.customer_id,
        c.name,
        os.surplus_date,
        os.id,
        CAST(19 AS int),
        N'—',
        CAST(0 AS decimal(14, 3)),
        os.amount,
        CONCAT(N'فائض — عقد أرشيف رقم ', CAST(ica.id AS nvarchar(20))),
        CAST(5 AS int),
        os.id
    FROM installment_surplus os
    INNER JOIN installment_contract_archives ica
        ON os.contractable_type = N'installment_contract_archive'
        AND os.contractable_id = ica.id
    INNER JOIN customers c ON c.id = ica.customer_id
    WHERE ica.customer_id IS NOT NULL
SQL;
    }

    protected function installmentSurplusArchivesSql(): string
    {
        return <<<'SQL'
SELECT
        ica.customer_id,
        c.name,
        isa.surplus_date,
        isa.id,
        CAST(19 AS int),
        N'—',
        CAST(0 AS decimal(14, 3)),
        isa.amount,
        CONCAT(N'فائض أرشيف — عقد رقم ', CAST(ica.id AS nvarchar(20))),
        CAST(6 AS int),
        isa.id
    FROM installment_surplus_archives isa
    INNER JOIN installment_contract_archives ica ON ica.id = isa.installment_contract_id
    INNER JOIN customers c ON c.id = ica.customer_id
    WHERE ica.customer_id IS NOT NULL
SQL;
    }

    protected function installmentReturnsSql(): string
    {
        return <<<'SQL'
SELECT
        ic.customer_id,
        c.name,
        s.suspended_date,
        s.id,
        CAST(20 AS int),
        N'—',
        s.amount,
        CAST(0 AS decimal(14, 3)),
        CONCAT(N'ترجيع — عقد رقم ', CAST(ic.id AS nvarchar(20))),
        CAST(7 AS int),
        s.id
    FROM installment_suspended s
    INNER JOIN installment_contracts ic ON ic.id = s.installment_contract_id
    INNER JOIN customers c ON c.id = ic.customer_id
    WHERE s.installment_contract_id IS NOT NULL
      AND ic.customer_id IS NOT NULL
SQL;
    }
};
