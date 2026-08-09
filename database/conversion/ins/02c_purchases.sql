/*
| INS → ERP conversion — step 02c: purchases
|
| Header : BenTaher.dbo.buys       → purchase_invoices
| Lines  : BenTaher.dbo.buy_tran   → purchase_invoice_lines
| Returns: BenTaher.dbo.tar_buy    → purchase_returns
|
| Mapping (same pattern as sales):
|   order_no    → id
|   jeha        → supplier_id
|   price_type  → payment_method_id  (INS 2→ERP 3 installment, INS 3→ERP 2 bank)
|   place_no    → warehouse_id (storage stores only)
|   tot1        → lines_subtotal
|   ksm         → discount
|   cash        → amount_paid
|   tot - cash  → balance
|   emp         → created_by (via new_erp.users.empno)
|
| Lines:
|   rec_no      → id
|   order_no    → purchase_invoice_id
|   item_no     → item_id / barcode
|   quant       → qty_primary
|   price_input → unit_cost_primary
|   remaining   → quant minus tar_buy returns for same invoice+item
|
| Verify:
|   SELECT COUNT(*) FROM BenTaher_erp.dbo.purchase_invoices;
|   SELECT COUNT(*) FROM BenTaher_erp.dbo.purchase_invoice_lines;
|   SELECT COUNT(*) FROM BenTaher_erp.dbo.purchase_returns;
*/

DECLARE @TargetCompany NVARCHAR(64) = N'BenTaher_erp';
DECLARE @FallbackUserId BIGINT = (
    SELECT MIN(id)
    FROM new_erp.dbo.users
    WHERE company = @TargetCompany
);

-- Headers
SET IDENTITY_INSERT BenTaher_erp.dbo.purchase_invoices ON;

INSERT INTO BenTaher_erp.dbo.purchase_invoices (
    id,
    invoice_date,
    supplier_id,
    payment_method_id,
    warehouse_id,
    lines_subtotal,
    amount_paid,
    balance,
    notes,
    created_by,
    created_at,
    updated_at,
    discount
)
SELECT
    b.order_no,
    b.order_date,
    b.jeha,
    CASE b.price_type WHEN 2 THEN 3 WHEN 3 THEN 2 ELSE b.price_type END,
    b.place_no,
    ISNULL(b.tot1, 0),
    ISNULL(b.cash, 0),
    ISNULL(b.tot, 0) - ISNULL(b.cash, 0),
    b.notes,
    COALESCE(u.id, @FallbackUserId),
    GETDATE(),
    GETDATE(),
    ISNULL(b.ksm, 0)
FROM BenTaher.dbo.buys AS b
LEFT JOIN new_erp.dbo.users AS u
    ON u.company = @TargetCompany
    AND u.empno = b.emp
WHERE EXISTS (SELECT 1 FROM BenTaher_erp.dbo.suppliers AS s WHERE s.id = b.jeha)
  AND EXISTS (SELECT 1 FROM BenTaher_erp.dbo.warehouses AS w WHERE w.id = b.place_no)
  AND EXISTS (
      SELECT 1
      FROM BenTaher_erp.dbo.payment_methods AS pm
      WHERE pm.id = CASE b.price_type WHEN 2 THEN 3 WHEN 3 THEN 2 ELSE b.price_type END
  )
  AND NOT EXISTS (
      SELECT 1
      FROM BenTaher_erp.dbo.purchase_invoices AS pi
      WHERE pi.id = b.order_no
  );

SET IDENTITY_INSERT BenTaher_erp.dbo.purchase_invoices OFF;

-- Lines
SET IDENTITY_INSERT BenTaher_erp.dbo.purchase_invoice_lines ON;

INSERT INTO BenTaher_erp.dbo.purchase_invoice_lines (
    id,
    purchase_invoice_id,
    item_id,
    barcode,
    qty_primary,
    qty_secondary,
    unit_cost_primary,
    line_cost_total,
    remaining_qty_primary,
    remaining_qty_secondary,
    purchase_return_id,
    expiry_date,
    created_by,
    created_at,
    updated_at
)
SELECT
    bt.rec_no,
    bt.order_no,
    bt.item_no,
    CAST(bt.item_no AS NVARCHAR(64)),
    ISNULL(bt.quant, 0),
    0,
    ISNULL(bt.price_input, 0),
    ISNULL(bt.quant, 0) * ISNULL(bt.price_input, 0),
    ISNULL(bt.quant, 0) - ISNULL((
        SELECT SUM(t.quant)
        FROM BenTaher.dbo.tar_buy AS t
        WHERE t.order_no = bt.order_no
          AND t.item_no = bt.item_no
    ), 0),
    0,
    NULL,
    NULL,
    COALESCE(u.id, @FallbackUserId),
    GETDATE(),
    GETDATE()
FROM BenTaher.dbo.buy_tran AS bt
INNER JOIN BenTaher_erp.dbo.purchase_invoices AS pi
    ON pi.id = bt.order_no
LEFT JOIN new_erp.dbo.users AS u
    ON u.company = @TargetCompany
    AND u.empno = bt.emp
WHERE EXISTS (SELECT 1 FROM BenTaher_erp.dbo.items AS i WHERE i.id = bt.item_no)
  AND NOT EXISTS (
      SELECT 1
      FROM BenTaher_erp.dbo.purchase_invoice_lines AS pil
      WHERE pil.id = bt.rec_no
  );

-- Patch created_by on lines inserted above (COALESCE in SELECT needs column list fix)
-- Applied via separate update for unmapped emp on lines:
UPDATE pil
SET pil.created_by = @FallbackUserId, pil.updated_at = GETDATE()
FROM BenTaher_erp.dbo.purchase_invoice_lines AS pil
WHERE pil.created_by IS NULL;

SET IDENTITY_INSERT BenTaher_erp.dbo.purchase_invoice_lines OFF;

-- Purchase returns (tar_buy)
SET IDENTITY_INSERT BenTaher_erp.dbo.purchase_returns ON;

INSERT INTO BenTaher_erp.dbo.purchase_returns (
    id,
    purchase_invoice_id,
    item_id,
    return_date,
    notes,
    created_by,
    created_at,
    updated_at,
    purchase_invoice_line_id,
    qty_primary,
    qty_secondary,
    unit_cost_primary,
    line_total
)
SELECT
    t.id,
    t.order_no,
    t.item_no,
    t.tar_date,
    NULL,
    COALESCE(u.id, @FallbackUserId),
    ISNULL(t.created_at, GETDATE()),
    ISNULL(t.updated_at, GETDATE()),
    bt.rec_no,
    ISNULL(t.quant, 0),
    0,
    ISNULL(bt.price_input, 0),
    ISNULL(t.quant, 0) * ISNULL(bt.price_input, 0)
FROM BenTaher.dbo.tar_buy AS t
INNER JOIN BenTaher.dbo.buy_tran AS bt
    ON bt.order_no = t.order_no
    AND bt.item_no = t.item_no
INNER JOIN BenTaher_erp.dbo.purchase_invoices AS pi
    ON pi.id = t.order_no
LEFT JOIN new_erp.dbo.users AS u
    ON u.company = @TargetCompany
    AND u.empno = t.emp
WHERE NOT EXISTS (
    SELECT 1
    FROM BenTaher_erp.dbo.purchase_returns AS pr
    WHERE pr.id = t.id
);

SET IDENTITY_INSERT BenTaher_erp.dbo.purchase_returns OFF;
