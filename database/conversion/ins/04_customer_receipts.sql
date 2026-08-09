/*
| INS → ERP conversion — step 04: customer receipts (cash receipts)
|
| Source : BenTaher.dbo.trans  (NOT kst_trans — that is installment deductions)
|
| Mapping:
|   tran_no     → id
|   tran_date   → receipt_date
|   jeha        → customer_id
|   order_no    → sales_invoice_id (when > 0 and invoice exists)
|   tran_type   → payment_method_id  (INS 2→ERP 3, INS 3→ERP 2, else same)
|   tran_who    → transaction_kind
|   imp_exp     → flow_direction
|   val         → amount
|   notes       → notes
|   emp         → created_by (users.empno, fallback min user id)
|
| Verify:
|   SELECT COUNT(*) FROM BenTaher_erp.dbo.customer_receipts;
*/

DECLARE @TargetCompany NVARCHAR(64) = N'BenTaher_erp';
DECLARE @FallbackUserId BIGINT = (
    SELECT MIN(id)
    FROM new_erp.dbo.users
    WHERE company = @TargetCompany
);

SET IDENTITY_INSERT BenTaher_erp.dbo.customer_receipts ON;

INSERT INTO BenTaher_erp.dbo.customer_receipts (
    id,
    receipt_date,
    customer_id,
    sales_invoice_id,
    payment_method_id,
    transaction_kind,
    flow_direction,
    amount,
    notes,
    sequence_no,
    cash_box_id,
    bank_account_id,
    warehouse_id,
    created_by,
    created_at,
    updated_at
)
SELECT
    t.tran_no,
    t.tran_date,
    t.jeha,
    CASE
        WHEN t.order_no > 0
         AND EXISTS (
             SELECT 1
             FROM BenTaher_erp.dbo.sales_invoices AS si
             WHERE si.id = t.order_no
         )
            THEN t.order_no
        ELSE NULL
    END,
    CASE t.tran_type
        WHEN 2 THEN 3
        WHEN 3 THEN 2
        ELSE ISNULL(t.tran_type, 1)
    END,
    t.tran_who,
    ISNULL(t.imp_exp, 1),
    ISNULL(t.val, 0),
    t.notes,
    t.tran_no,
    CASE
        WHEN CASE t.tran_type WHEN 2 THEN 3 WHEN 3 THEN 2 ELSE ISNULL(t.tran_type, 1) END = 1 THEN 1
        ELSE NULL
    END,
    CASE
        WHEN CASE t.tran_type WHEN 2 THEN 3 WHEN 3 THEN 2 ELSE ISNULL(t.tran_type, 1) END = 2 THEN 1
        ELSE NULL
    END,
    NULL,
    COALESCE(u.id, @FallbackUserId),
    COALESCE(t.inp_date, t.tran_date, GETDATE()),
    COALESCE(t.inp_date, t.tran_date, GETDATE())
FROM BenTaher.dbo.trans AS t
INNER JOIN BenTaher.dbo.jeha AS j ON j.jeha_no = t.jeha AND ISNULL(j.jeha_type, 1) <> 2
LEFT JOIN new_erp.dbo.users AS u
    ON u.company = @TargetCompany
    AND u.empno = t.emp
WHERE EXISTS (SELECT 1 FROM BenTaher_erp.dbo.customers AS c WHERE c.id = t.jeha)
  AND EXISTS (
      SELECT 1
      FROM BenTaher_erp.dbo.payment_methods AS pm
      WHERE pm.id = CASE t.tran_type WHEN 2 THEN 3 WHEN 3 THEN 2 ELSE ISNULL(t.tran_type, 1) END
  )
  AND NOT EXISTS (
      SELECT 1
      FROM BenTaher_erp.dbo.customer_receipts AS cr
      WHERE cr.id = t.tran_no
  );

SET IDENTITY_INSERT BenTaher_erp.dbo.customer_receipts OFF;
