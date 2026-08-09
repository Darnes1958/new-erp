/*
| INS → ERP — step 13: verification report (BenTaher)
|
| Run after all conversion steps. Compare INS source counts with ERP targets.
| Expected differences (documented):
|   wrong_deductions   ERP +21 (synthetic rows for orphan tar_kst type 2)
|   users              ERP +1  (legacy admin row from 01a fix)
|   deductions         ±few rows (ksm filter edge cases)
|   cheques            only active-contract chk_tasleem (not all 1413)
|   fifo               no INS source (rebuilt in step 07)
|
| Usage:
|   sqlcmd -S ... -i database/conversion/ins/13_verify_bentaher.sql
*/

SET NOCOUNT ON;

PRINT '=== BenTaher INS → ERP verification ===';
PRINT '';

SELECT
    v.item,
    v.erp,
    v.ins,
    CASE
        WHEN v.ins IS NULL THEN N'—'
        WHEN v.erp = v.ins THEN N'OK'
        ELSE CAST(v.erp - v.ins AS NVARCHAR(20))
    END AS diff
FROM (
    SELECT N'users' AS item,
        (SELECT COUNT(*) FROM new_erp.dbo.users WHERE company = N'BenTaher_erp') AS erp,
        (SELECT COUNT(*) FROM useradmin.dbo.users WHERE company = N'BenTaher') AS ins
    UNION ALL
    SELECT N'customers',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.customers),
        (SELECT COUNT(*) FROM BenTaher.dbo.jeha WHERE ISNULL(jeha_type, 1) <> 2)
    UNION ALL
    SELECT N'suppliers',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.suppliers),
        (SELECT COUNT(*) FROM BenTaher.dbo.jeha WHERE jeha_type = 2)
    UNION ALL
    SELECT N'warehouses',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.warehouses),
        (SELECT COUNT(*) FROM BenTaher.dbo.stores_names)
            + (SELECT COUNT(*) FROM BenTaher.dbo.halls_names)
    UNION ALL
    SELECT N'items',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.items),
        (SELECT COUNT(*) FROM BenTaher.dbo.items)
    UNION ALL
    SELECT N'sales_invoices',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.sales_invoices),
        (SELECT COUNT(*) FROM BenTaher.dbo.sells)
    UNION ALL
    SELECT N'sales_invoice_lines',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.sales_invoice_lines),
        (SELECT COUNT(*) FROM BenTaher.dbo.sell_tran)
    UNION ALL
    SELECT N'purchase_invoices',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.purchase_invoices),
        (SELECT COUNT(*) FROM BenTaher.dbo.buys)
    UNION ALL
    SELECT N'fifo_allocations',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.fifo_allocations),
        NULL
    UNION ALL
    SELECT N'installment_contracts',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.installment_contracts),
        (SELECT COUNT(*) FROM BenTaher.dbo.main)
    UNION ALL
    SELECT N'contract_archives',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.installment_contract_archives),
        (SELECT COUNT(*) FROM BenTaher.dbo.MainArc)
    UNION ALL
    SELECT N'deductions (ksm>0)',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.installment_deductions),
        (SELECT COUNT(*) FROM BenTaher.dbo.kst_trans WHERE ksm > 0)
    UNION ALL
    SELECT N'deduction_archives',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.installment_deduction_archives),
        (SELECT COUNT(*) FROM BenTaher.dbo.TransArc WHERE ksm > 0)
    UNION ALL
    SELECT N'installment_surplus',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.installment_surplus),
        (SELECT COUNT(*) FROM BenTaher.dbo.over_kst)
    UNION ALL
    SELECT N'surplus_archives',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.installment_surplus_archives),
        (SELECT COUNT(*) FROM BenTaher.dbo.over_kst_a)
    UNION ALL
    SELECT N'wrong_deductions',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.wrong_deductions),
        (SELECT COUNT(*) FROM BenTaher.dbo.wrong_kst)
    UNION ALL
    SELECT N'installment_suspended',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.installment_suspended),
        (SELECT COUNT(*) FROM BenTaher.dbo.tar_kst)
    UNION ALL
    SELECT N'stops (all)',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.installment_stops)
            + (SELECT COUNT(*) FROM BenTaher_erp.dbo.installment_stops_without_contract),
        (SELECT COUNT(*) FROM BenTaher.dbo.stop_kst)
    UNION ALL
    SELECT N'deduction_batches',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.deduction_batches),
        (SELECT COUNT(*) FROM BenTaher.dbo.hafitha)
    UNION ALL
    SELECT N'batch_lines',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.deduction_batch_lines),
        (SELECT COUNT(*) FROM BenTaher.dbo.hafitha_tran)
    UNION ALL
    SELECT N'installment_cheques',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.installment_cheques),
        (SELECT COUNT(*)
         FROM BenTaher.dbo.chk_tasleem AS ct
         INNER JOIN BenTaher_erp.dbo.installment_contracts AS c ON c.id = ct.no)
    UNION ALL
    SELECT N'customer_receipts',
        (SELECT COUNT(*) FROM BenTaher_erp.dbo.customer_receipts),
        (SELECT COUNT(*)
         FROM BenTaher.dbo.trans AS t
         INNER JOIN BenTaher.dbo.jeha AS j
             ON j.jeha_no = t.jeha AND ISNULL(j.jeha_type, 1) <> 2)
) AS v
ORDER BY v.item;

PRINT '';
PRINT '=== Contract financial totals ===';

SELECT
    N'INS main' AS source,
    SUM(CAST(m.sul_pay AS DECIMAL(18, 2))) AS total_paid,
    SUM(CAST(m.raseed AS DECIMAL(18, 2))) AS balance
FROM BenTaher.dbo.main AS m
UNION ALL
SELECT
    N'ERP contracts',
    SUM(CAST(c.total_paid AS DECIMAL(18, 2))),
    SUM(CAST(c.balance AS DECIMAL(18, 2)))
FROM BenTaher_erp.dbo.installment_contracts AS c;
