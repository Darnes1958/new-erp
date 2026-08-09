/*
| INS → ERP — step 08: installment deductions (import rule + cleanup)
|
| INS kst_trans / TransArc reserve one row per installment at contract creation.
| Only import rows with actual deduction: ksm > 0  → deducted_amount
|
| kst = scheduled installment amount (due) — NOT imported as deduction by itself
| ksm = amount actually deducted — this becomes deducted_amount
|
| Cleanup (if full import ran before this rule):
|   DELETE ... WHERE deducted_amount IS NULL OR deducted_amount = 0;
|
| Verify:
|   SELECT COUNT(*) FROM BenTaher_erp.dbo.installment_deductions;
|   -- expect: kst_trans rows where ksm > 0
*/

-- Cleanup placeholder rows already imported
DELETE FROM BenTaher_erp.dbo.installment_deductions
WHERE deducted_amount IS NULL OR deducted_amount = 0;

DELETE FROM BenTaher_erp.dbo.installment_deduction_archives
WHERE deducted_amount IS NULL OR deducted_amount = 0;

-- Fresh import template (run only on empty tables):
/*
SET IDENTITY_INSERT BenTaher_erp.dbo.installment_deductions ON;

INSERT INTO BenTaher_erp.dbo.installment_deductions (...)
SELECT ...
FROM BenTaher.dbo.kst_trans AS k
WHERE k.ksm IS NOT NULL AND k.ksm <> 0
  AND NOT EXISTS (...);

SET IDENTITY_INSERT BenTaher_erp.dbo.installment_deductions OFF;
*/
