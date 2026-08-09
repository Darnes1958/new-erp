<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    use MigratesCompanyDatabases;

    public function up(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            DB::connection($connection)->statement('IF OBJECT_ID(N\'cash_box_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW cash_box_ledger_entries');
            DB::connection($connection)->statement('IF OBJECT_ID(N\'bank_account_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW bank_account_ledger_entries');

            DB::connection($connection)->statement($this->cashBoxLedgerViewSql());
            DB::connection($connection)->statement($this->bankAccountLedgerViewSql());
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            DB::connection($connection)->statement('IF OBJECT_ID(N\'cash_box_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW cash_box_ledger_entries');
            DB::connection($connection)->statement('IF OBJECT_ID(N\'bank_account_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW bank_account_ledger_entries');
        });
    }

    protected function cashBoxLedgerViewSql(): string
    {
        return <<<SQL
CREATE VIEW cash_box_ledger_entries AS
SELECT
    lines.cash_box_id,
    lines.rep_date,
    lines.source_id AS id,
    ROW_NUMBER() OVER (
        PARTITION BY lines.cash_box_id
        ORDER BY lines.rep_date, lines.source_order, lines.sort_id
    ) AS idd,
    lines.transaction_kind,
    lines.party_name,
    lines.mden,
    lines.daen,
    lines.document_no,
    lines.notes
FROM (
    SELECT
        cr.cash_box_id,
        cr.receipt_date AS rep_date,
        cr.id AS source_id,
        cr.transaction_kind,
        c.name AS party_name,
        CASE WHEN cr.flow_direction = 1 THEN cr.amount ELSE CAST(0 AS decimal(14, 3)) END AS mden,
        CASE WHEN cr.flow_direction = 0 THEN cr.amount ELSE CAST(0 AS decimal(14, 3)) END AS daen,
        COALESCE(cr.sales_invoice_id, cr.id) AS document_no,
        cr.notes,
        CAST(2 AS int) AS source_order,
        cr.id AS sort_id
    FROM customer_receipts cr
    LEFT JOIN customers c ON c.id = cr.customer_id
    WHERE cr.cash_box_id IS NOT NULL

    UNION ALL

    SELECT
        sp.cash_box_id,
        sp.payment_date,
        sp.id,
        sp.transaction_kind,
        s.name,
        CASE WHEN sp.flow_direction = 1 THEN sp.amount ELSE CAST(0 AS decimal(14, 3)) END,
        CASE WHEN sp.flow_direction = 0 THEN sp.amount ELSE CAST(0 AS decimal(14, 3)) END,
        COALESCE(sp.purchase_invoice_id, sp.id),
        sp.notes,
        CAST(3 AS int),
        sp.id
    FROM supplier_payments sp
    LEFT JOIN suppliers s ON s.id = sp.supplier_id
    WHERE sp.cash_box_id IS NOT NULL

    UNION ALL

    SELECT
        e.cash_box_id,
        e.expense_date,
        e.id,
        CAST(30 AS int),
        et.name,
        e.amount,
        CAST(0 AS decimal(14, 3)),
        e.id,
        e.notes,
        CAST(4 AS int),
        e.id
    FROM expenses e
    LEFT JOIN expense_types et ON et.id = e.expense_type_id
    WHERE e.cash_box_id IS NOT NULL

    UNION ALL

    SELECT
        st.cash_box_id,
        st.transaction_date,
        st.id,
        CAST(31 AS int),
        sp.name,
        st.amount,
        CAST(0 AS decimal(14, 3)),
        st.id,
        st.notes,
        CAST(5 AS int),
        st.id
    FROM salary_transactions st
    LEFT JOIN salary_profiles sp ON sp.id = st.salary_profile_id
    WHERE st.cash_box_id IS NOT NULL

    UNION ALL

    SELECT
        rt.cash_box_id,
        rt.transaction_date,
        rt.id,
        CAST(32 AS int),
        rp.name,
        rt.amount,
        CAST(0 AS decimal(14, 3)),
        rt.id,
        rt.notes,
        CAST(6 AS int),
        rt.id
    FROM rent_transactions rt
    LEFT JOIN rent_profiles rp ON rp.id = rt.rent_profile_id
    WHERE rt.cash_box_id IS NOT NULL

    UNION ALL

    SELECT
        ft.from_cash_box_id,
        ft.transfer_date,
        ft.id,
        CAST(33 AS int),
        N'تحويل صادر',
        ft.amount,
        CAST(0 AS decimal(14, 3)),
        ft.id,
        ft.notes,
        CAST(7 AS int),
        ft.id
    FROM fund_transfers ft
    WHERE ft.from_cash_box_id IS NOT NULL

    UNION ALL

    SELECT
        ft.to_cash_box_id,
        ft.transfer_date,
        ft.id,
        CAST(34 AS int),
        N'تحويل وارد',
        CAST(0 AS decimal(14, 3)),
        ft.amount,
        ft.id,
        ft.notes,
        CAST(8 AS int),
        ft.id
    FROM fund_transfers ft
    WHERE ft.to_cash_box_id IS NOT NULL
) AS lines
SQL;
    }

    protected function bankAccountLedgerViewSql(): string
    {
        return <<<SQL
CREATE VIEW bank_account_ledger_entries AS
SELECT
    lines.bank_account_id,
    lines.rep_date,
    lines.source_id AS id,
    ROW_NUMBER() OVER (
        PARTITION BY lines.bank_account_id
        ORDER BY lines.rep_date, lines.source_order, lines.sort_id
    ) AS idd,
    lines.transaction_kind,
    lines.party_name,
    lines.mden,
    lines.daen,
    lines.document_no,
    lines.notes
FROM (
    SELECT
        cr.bank_account_id,
        cr.receipt_date AS rep_date,
        cr.id AS source_id,
        cr.transaction_kind,
        c.name AS party_name,
        CASE WHEN cr.flow_direction = 1 THEN cr.amount ELSE CAST(0 AS decimal(14, 3)) END AS mden,
        CASE WHEN cr.flow_direction = 0 THEN cr.amount ELSE CAST(0 AS decimal(14, 3)) END AS daen,
        COALESCE(cr.sales_invoice_id, cr.id) AS document_no,
        cr.notes,
        CAST(2 AS int) AS source_order,
        cr.id AS sort_id
    FROM customer_receipts cr
    LEFT JOIN customers c ON c.id = cr.customer_id
    WHERE cr.bank_account_id IS NOT NULL

    UNION ALL

    SELECT
        sp.bank_account_id,
        sp.payment_date,
        sp.id,
        sp.transaction_kind,
        s.name,
        CASE WHEN sp.flow_direction = 1 THEN sp.amount ELSE CAST(0 AS decimal(14, 3)) END,
        CASE WHEN sp.flow_direction = 0 THEN sp.amount ELSE CAST(0 AS decimal(14, 3)) END,
        COALESCE(sp.purchase_invoice_id, sp.id),
        sp.notes,
        CAST(3 AS int),
        sp.id
    FROM supplier_payments sp
    LEFT JOIN suppliers s ON s.id = sp.supplier_id
    WHERE sp.bank_account_id IS NOT NULL

    UNION ALL

    SELECT
        e.bank_account_id,
        e.expense_date,
        e.id,
        CAST(30 AS int),
        et.name,
        e.amount,
        CAST(0 AS decimal(14, 3)),
        e.id,
        e.notes,
        CAST(4 AS int),
        e.id
    FROM expenses e
    LEFT JOIN expense_types et ON et.id = e.expense_type_id
    WHERE e.bank_account_id IS NOT NULL

    UNION ALL

    SELECT
        st.bank_account_id,
        st.transaction_date,
        st.id,
        CAST(31 AS int),
        sp.name,
        st.amount,
        CAST(0 AS decimal(14, 3)),
        st.id,
        st.notes,
        CAST(5 AS int),
        st.id
    FROM salary_transactions st
    LEFT JOIN salary_profiles sp ON sp.id = st.salary_profile_id
    WHERE st.bank_account_id IS NOT NULL

    UNION ALL

    SELECT
        rt.bank_account_id,
        rt.transaction_date,
        rt.id,
        CAST(32 AS int),
        rp.name,
        rt.amount,
        CAST(0 AS decimal(14, 3)),
        rt.id,
        rt.notes,
        CAST(6 AS int),
        rt.id
    FROM rent_transactions rt
    LEFT JOIN rent_profiles rp ON rp.id = rt.rent_profile_id
    WHERE rt.bank_account_id IS NOT NULL

    UNION ALL

    SELECT
        ft.from_bank_account_id,
        ft.transfer_date,
        ft.id,
        CAST(33 AS int),
        N'تحويل صادر',
        ft.amount,
        CAST(0 AS decimal(14, 3)),
        ft.id,
        ft.notes,
        CAST(7 AS int),
        ft.id
    FROM fund_transfers ft
    WHERE ft.from_bank_account_id IS NOT NULL

    UNION ALL

    SELECT
        ft.to_bank_account_id,
        ft.transfer_date,
        ft.id,
        CAST(34 AS int),
        N'تحويل وارد',
        CAST(0 AS decimal(14, 3)),
        ft.amount,
        ft.id,
        ft.notes,
        CAST(8 AS int),
        ft.id
    FROM fund_transfers ft
    WHERE ft.to_bank_account_id IS NOT NULL
) AS lines
SQL;
    }
};
