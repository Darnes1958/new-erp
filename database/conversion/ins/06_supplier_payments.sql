/*
| INS → ERP — step 06: supplier payments
|
| Source: BenTaher.dbo.trans WHERE jeha.jeha_type = 2 (suppliers)
| NOT customer receipts (jeha_type <> 2) — see 04_customer_receipts.sql
|
| Mapping: same columns as trans → supplier_payments
|   order_no → purchase_invoice_id (when > 0)
|   tran_type → payment_method_id (INS 2→ERP 3, INS 3→ERP 2)
|   cash(1) → cash_box_id=1 | bank(2) → bank_account_id=1
|
| Run 05_default_cash_accounts.sql first.
*/

DECLARE @TargetCompany NVARCHAR(64) = N'BenTaher_erp';
DECLARE @FallbackUserId BIGINT = (
    SELECT MIN(id) FROM new_erp.dbo.users WHERE company = @TargetCompany
);

SET IDENTITY_INSERT BenTaher_erp.dbo.supplier_payments ON;

INSERT INTO BenTaher_erp.dbo.supplier_payments (
    id,
    payment_date,
    supplier_id,
    purchase_invoice_id,
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
         AND EXISTS (SELECT 1 FROM BenTaher_erp.dbo.purchase_invoices pi WHERE pi.id = t.order_no)
            THEN t.order_no
        ELSE NULL
    END,
    CASE t.tran_type WHEN 2 THEN 3 WHEN 3 THEN 2 ELSE ISNULL(t.tran_type, 1) END,
    t.tran_who,
    ISNULL(t.imp_exp, 1),
    ISNULL(t.val, 0),
    t.notes,
    t.tran_no,
    CASE WHEN CASE t.tran_type WHEN 2 THEN 3 WHEN 3 THEN 2 ELSE ISNULL(t.tran_type, 1) END = 1 THEN 1 END,
    CASE WHEN CASE t.tran_type WHEN 2 THEN 3 WHEN 3 THEN 2 ELSE ISNULL(t.tran_type, 1) END = 2 THEN 1 END,
    NULL,
    COALESCE(u.id, @FallbackUserId),
    COALESCE(t.inp_date, t.tran_date, GETDATE()),
    COALESCE(t.inp_date, t.tran_date, GETDATE())
FROM BenTaher.dbo.trans AS t
INNER JOIN BenTaher.dbo.jeha AS j ON j.jeha_no = t.jeha AND j.jeha_type = 2
LEFT JOIN new_erp.dbo.users AS u ON u.company = @TargetCompany AND u.empno = t.emp
WHERE EXISTS (SELECT 1 FROM BenTaher_erp.dbo.suppliers s WHERE s.id = t.jeha)
  AND NOT EXISTS (SELECT 1 FROM BenTaher_erp.dbo.supplier_payments sp WHERE sp.id = t.tran_no);

SET IDENTITY_INSERT BenTaher_erp.dbo.supplier_payments OFF;
