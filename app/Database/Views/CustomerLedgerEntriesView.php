<?php

namespace App\Database\Views;

use Illuminate\Support\Facades\Schema;

class CustomerLedgerEntriesView
{
    public function createSql(string $connection, bool $includeReturnedAmountInSalesInvoice = true): string
    {
        $lines = $this->unionLinesSql($connection, $includeReturnedAmountInSalesInvoice, customerFilter: false);

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

    /**
     * Union of ledger lines. When \$bindCustomer is true, each branch filters with " = ?"
     * so callers can bind the same customer id once per branch (fast path for كشف زبون).
     *
     * @return array{0: string, 1: int} SQL + number of customer bindings required
     */
    public function unionLinesSqlWithBindings(
        string $connection,
        bool $includeReturnedAmountInSalesInvoice = true,
        bool $bindCustomer = false,
    ): array {
        $sql = $this->unionLinesSql($connection, $includeReturnedAmountInSalesInvoice, $bindCustomer);
        $bindings = $bindCustomer ? $this->countCustomerBindings($connection, $includeReturnedAmountInSalesInvoice) : 0;

        return [$sql, $bindings];
    }

    protected function unionLinesSql(
        string $connection,
        bool $includeReturnedAmountInSalesInvoice,
        bool $customerFilter,
    ): string {
        $parts = [$this->coreLinesSql($includeReturnedAmountInSalesInvoice, $customerFilter)];

        if (Schema::connection($connection)->hasTable('installment_deductions')) {
            $parts[] = $this->installmentDeductionsSql($customerFilter);
        }

        if (
            Schema::connection($connection)->hasTable('installment_deduction_archives')
            && Schema::connection($connection)->hasTable('installment_contract_archives')
        ) {
            $parts[] = $this->installmentDeductionArchivesSql($customerFilter);
        }

        if (Schema::connection($connection)->hasTable('installment_surplus')) {
            $parts[] = $this->installmentSurplusSql($customerFilter);
        }

        if (Schema::connection($connection)->hasTable('installment_surplus_archives')) {
            $parts[] = $this->installmentSurplusArchivesSql($customerFilter);
        }

        if (Schema::connection($connection)->hasTable('installment_suspended')) {
            $parts[] = $this->installmentReturnsSql($connection, $customerFilter);
        }

        return implode("\n\n    UNION ALL\n\n", $parts);
    }

    protected function countCustomerBindings(string $connection, bool $includeReturnedAmountInSalesInvoice): int
    {
        // core: sales + receipts + returns = 3
        $count = 3;

        if (Schema::connection($connection)->hasTable('installment_deductions')) {
            $count++;
        }

        if (
            Schema::connection($connection)->hasTable('installment_deduction_archives')
            && Schema::connection($connection)->hasTable('installment_contract_archives')
        ) {
            $count++;
        }

        if (Schema::connection($connection)->hasTable('installment_surplus')) {
            // active contracts + archive morph
            $count += 2;
        }

        if (Schema::connection($connection)->hasTable('installment_surplus_archives')) {
            $count++;
        }

        if (Schema::connection($connection)->hasTable('installment_suspended')) {
            $count++;
            if (Schema::connection($connection)->hasTable('installment_contract_archives')) {
                $count++;
            }
        }

        return $count;
    }

    protected function customerPredicate(string $alias, bool $customerFilter): string
    {
        return $customerFilter ? " AND {$alias}.customer_id = ?" : '';
    }

    protected function coreLinesSql(bool $includeReturnedAmountInSalesInvoice, bool $customerFilter): string
    {
        $salesMden = $includeReturnedAmountInSalesInvoice
            ? 'CAST(si.grand_total + COALESCE(sr_tot.returned_total, 0) AS decimal(14, 3)) AS mden'
            : 'CAST(si.grand_total AS decimal(14, 3)) AS mden';

        $salesJoin = $includeReturnedAmountInSalesInvoice
            ? <<<'SQL'
    LEFT JOIN (
        SELECT sales_invoice_id, SUM(line_total) AS returned_total
        FROM sales_returns
        GROUP BY sales_invoice_id
    ) sr_tot ON sr_tot.sales_invoice_id = si.id
SQL
            : '';

        $siFilter = $this->customerPredicate('si', $customerFilter);
        $crFilter = $this->customerPredicate('cr', $customerFilter);
        $siReturnFilter = $this->customerPredicate('si', $customerFilter);

        return <<<SQL
SELECT
        si.customer_id,
        c.name AS customer_name,
        si.invoice_date AS rep_date,
        si.id AS source_id,
        CAST(7 AS int) AS transaction_kind,
        pm.name AS payment_method_name,
        {$salesMden},
        CAST(0 AS decimal(14, 3)) AS daen,
        si.notes,
        CAST(1 AS int) AS source_order,
        si.id AS sort_id
    FROM sales_invoices si
    INNER JOIN customers c ON c.id = si.customer_id
    LEFT JOIN payment_methods pm ON pm.id = si.payment_method_id
{$salesJoin}
    WHERE si.customer_id IS NOT NULL{$siFilter}

    UNION ALL

    SELECT
        cr.customer_id,
        c.name,
        cr.receipt_date,
        cr.id,
        cr.transaction_kind,
        pm.name,
        CASE WHEN cr.transaction_kind IN (2, 4, 5) THEN cr.amount ELSE CAST(0 AS decimal(14, 3)) END,
        CASE WHEN cr.transaction_kind IN (1, 3, 6) THEN cr.amount ELSE CAST(0 AS decimal(14, 3)) END,
        cr.notes,
        CAST(2 AS int),
        cr.id
    FROM customer_receipts cr
    INNER JOIN customers c ON c.id = cr.customer_id
    LEFT JOIN payment_methods pm ON pm.id = cr.payment_method_id
    WHERE 1 = 1{$crFilter}

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
    WHERE si.customer_id IS NOT NULL{$siReturnFilter}
SQL;
    }

    protected function installmentDeductionsSql(bool $customerFilter): string
    {
        $filter = $this->customerPredicate('ic', $customerFilter);

        return <<<SQL
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
    WHERE ic.customer_id IS NOT NULL{$filter}
SQL;
    }

    protected function installmentDeductionArchivesSql(bool $customerFilter): string
    {
        $filter = $this->customerPredicate('ica', $customerFilter);

        return <<<SQL
SELECT
        ica.customer_id,
        c.name,
        ida.deduction_date,
        ida.id,
        CAST(18 AS int),
        CASE ida.deduction_type_id
            WHEN 1 THEN N'نقداً'
            WHEN 2 THEN N'المصرف'
            WHEN 3 THEN N'صك'
            WHEN 4 THEN N'إلكتروني'
            ELSE N'—'
        END,
        CAST(0 AS decimal(14, 3)),
        ida.deducted_amount,
        COALESCE(ida.notes, CONCAT(N'عقد أرشيف رقم ', CAST(ica.id AS nvarchar(20)))),
        CAST(5 AS int),
        ida.id
    FROM installment_deduction_archives ida
    INNER JOIN installment_contract_archives ica ON ica.id = ida.installment_contract_id
    INNER JOIN customers c ON c.id = ica.customer_id
    WHERE ica.customer_id IS NOT NULL{$filter}
SQL;
    }

    protected function installmentSurplusSql(bool $customerFilter): string
    {
        $icFilter = $this->customerPredicate('ic', $customerFilter);
        $icaFilter = $this->customerPredicate('ica', $customerFilter);

        return <<<SQL
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
        CAST(6 AS int),
        os.id
    FROM installment_surplus os
    INNER JOIN installment_contracts ic
        ON os.contractable_type = N'installment_contract'
        AND os.contractable_id = ic.id
    INNER JOIN customers c ON c.id = ic.customer_id
    WHERE ic.customer_id IS NOT NULL{$icFilter}

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
        CAST(6 AS int),
        os.id
    FROM installment_surplus os
    INNER JOIN installment_contract_archives ica
        ON os.contractable_type = N'installment_contract_archive'
        AND os.contractable_id = ica.id
    INNER JOIN customers c ON c.id = ica.customer_id
    WHERE ica.customer_id IS NOT NULL{$icaFilter}
SQL;
    }

    protected function installmentSurplusArchivesSql(bool $customerFilter): string
    {
        $filter = $this->customerPredicate('ica', $customerFilter);

        return <<<SQL
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
        CAST(7 AS int),
        isa.id
    FROM installment_surplus_archives isa
    INNER JOIN installment_contract_archives ica ON ica.id = isa.installment_contract_id
    INNER JOIN customers c ON c.id = ica.customer_id
    WHERE ica.customer_id IS NOT NULL{$filter}
SQL;
    }

    protected function installmentReturnsSql(string $connection, bool $customerFilter): string
    {
        $icFilter = $this->customerPredicate('ic', $customerFilter);

        $sql = <<<SQL
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
        CAST(8 AS int),
        s.id
    FROM installment_suspended s
    INNER JOIN installment_contracts ic ON ic.id = s.installment_contract_id
    INNER JOIN customers c ON c.id = ic.customer_id
    WHERE s.installment_contract_id IS NOT NULL
      AND ic.customer_id IS NOT NULL{$icFilter}
SQL;

        if (! Schema::connection($connection)->hasTable('installment_contract_archives')) {
            return $sql;
        }

        $icaFilter = $this->customerPredicate('ica', $customerFilter);

        return $sql.<<<SQL


    UNION ALL

    SELECT
        ica.customer_id,
        c.name,
        s.suspended_date,
        s.id,
        CAST(20 AS int),
        N'—',
        s.amount,
        CAST(0 AS decimal(14, 3)),
        CONCAT(N'ترجيع — عقد أرشيف رقم ', CAST(ica.id AS nvarchar(20))),
        CAST(8 AS int),
        s.id
    FROM installment_suspended s
    INNER JOIN installment_contract_archives ica
        ON s.contractable_type = N'installment_contract_archive'
        AND s.contractable_id = ica.id
    INNER JOIN customers c ON c.id = ica.customer_id
    WHERE ica.customer_id IS NOT NULL{$icaFilter}
SQL;
    }
}
