/*
| Fix BenTaher_erp mapping corrections:
|
| 1) warehouse_type: stores=0, halls=1 (ERP standard, not 1/2)
| 2) payment_method_id: INS 2 (تقسيط) ↔ ERP 3, INS 3 (صك) ↔ ERP 2
| 3) created_by: unmapped emp → smallest BenTaher_erp user id
*/

DECLARE @TargetCompany NVARCHAR(64) = N'BenTaher_erp';
DECLARE @FallbackUserId BIGINT = (
    SELECT MIN(id)
    FROM new_erp.dbo.users
    WHERE company = @TargetCompany
);

-- 1) Warehouse types
UPDATE BenTaher_erp.dbo.warehouses
SET warehouse_type = 0, updated_at = GETDATE()
WHERE id < 10000;

UPDATE BenTaher_erp.dbo.warehouses
SET warehouse_type = 1, updated_at = GETDATE()
WHERE id >= 10000;

-- 2) Payment methods — swap row metadata (names/rates from INS were on reversed ids)
DECLARE @n2 NVARCHAR(255), @n3 NVARCHAR(255);
DECLARE @r2 DECIMAL(18, 3), @r3 DECIMAL(18, 3);
DECLARE @v2 DECIMAL(18, 3), @v3 DECIMAL(18, 3);
DECLARE @d2 TINYINT, @d3 TINYINT;

SELECT @n2 = name, @r2 = rate, @v2 = adjustment_value, @d2 = adjustment_direction
FROM BenTaher_erp.dbo.payment_methods WHERE id = 2;

SELECT @n3 = name, @r3 = rate, @v3 = adjustment_value, @d3 = adjustment_direction
FROM BenTaher_erp.dbo.payment_methods WHERE id = 3;

UPDATE BenTaher_erp.dbo.payment_methods
SET
    name = @n3,
    code = N'bank',
    rate = @r3,
    adjustment_value = @v3,
    adjustment_direction = @d3,
    updated_at = GETDATE()
WHERE id = 2;

UPDATE BenTaher_erp.dbo.payment_methods
SET
    name = @n2,
    code = N'installment',
    rate = @r2,
    adjustment_value = @v2,
    adjustment_direction = @d2,
    updated_at = GETDATE()
WHERE id = 3;

-- Swap FK references 2 <-> 3
UPDATE BenTaher_erp.dbo.sales_invoices
SET payment_method_id = CASE payment_method_id WHEN 2 THEN 3 WHEN 3 THEN 2 ELSE payment_method_id END;

UPDATE BenTaher_erp.dbo.purchase_invoices
SET payment_method_id = CASE payment_method_id WHEN 2 THEN 3 WHEN 3 THEN 2 ELSE payment_method_id END;

ALTER TABLE BenTaher_erp.dbo.item_prices NOCHECK CONSTRAINT item_prices_payment_method_id_foreign;

UPDATE BenTaher_erp.dbo.item_prices
SET payment_method_id = payment_method_id + 900
WHERE payment_method_id IN (2, 3);

UPDATE BenTaher_erp.dbo.item_prices
SET payment_method_id = CASE payment_method_id WHEN 902 THEN 3 WHEN 903 THEN 2 ELSE payment_method_id END
WHERE payment_method_id IN (902, 903);

ALTER TABLE BenTaher_erp.dbo.item_prices CHECK CONSTRAINT item_prices_payment_method_id_foreign;

-- 3) created_by fallback for unmapped emp
UPDATE BenTaher_erp.dbo.sales_invoices
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;

UPDATE BenTaher_erp.dbo.sales_invoice_lines
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;

UPDATE BenTaher_erp.dbo.purchase_invoices
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;

UPDATE BenTaher_erp.dbo.purchase_invoice_lines
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;

UPDATE BenTaher_erp.dbo.purchase_returns
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;

UPDATE BenTaher_erp.dbo.installment_contracts
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;
