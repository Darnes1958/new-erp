/*
| INS → ERP — default cash box & bank account
|
| Legacy INS has no kazena/bank master — seed one row each and backfill:
|   payment_method_id = 1 (cash)  → cash_box_id = 1
|   payment_method_id = 2 (bank)  → bank_account_id = 1
|   payment_method_id = 3 (installment) → both NULL
|
| Run after 04_customer_receipts.sql (or any payment import step).
*/

DECLARE @MainCashBoxId BIGINT = 1;
DECLARE @MainBankAccountId BIGINT = 1;

IF NOT EXISTS (SELECT 1 FROM BenTaher_erp.dbo.cash_boxes WHERE id = @MainCashBoxId)
BEGIN
    SET IDENTITY_INSERT BenTaher_erp.dbo.cash_boxes ON;

    INSERT INTO BenTaher_erp.dbo.cash_boxes (
        id, name, opening_balance, assigned_user_id, is_active, created_at, updated_at
    )
    VALUES (@MainCashBoxId, N'الخزينة الرئيسة', 0, NULL, 1, GETDATE(), GETDATE());

    SET IDENTITY_INSERT BenTaher_erp.dbo.cash_boxes OFF;
END;

IF NOT EXISTS (SELECT 1 FROM BenTaher_erp.dbo.bank_accounts WHERE id = @MainBankAccountId)
BEGIN
    SET IDENTITY_INSERT BenTaher_erp.dbo.bank_accounts ON;

    INSERT INTO BenTaher_erp.dbo.bank_accounts (
        id, name, account_number, opening_balance, is_active, created_at, updated_at
    )
    VALUES (@MainBankAccountId, N'الحساب المصرفي الرئيسي', NULL, 0, 1, GETDATE(), GETDATE());

    SET IDENTITY_INSERT BenTaher_erp.dbo.bank_accounts OFF;
END;

UPDATE BenTaher_erp.dbo.customer_receipts
SET cash_box_id = @MainCashBoxId, bank_account_id = NULL, updated_at = GETDATE()
WHERE payment_method_id = 1 AND cash_box_id IS NULL;

UPDATE BenTaher_erp.dbo.customer_receipts
SET bank_account_id = @MainBankAccountId, cash_box_id = NULL, updated_at = GETDATE()
WHERE payment_method_id = 2 AND bank_account_id IS NULL;

UPDATE BenTaher_erp.dbo.supplier_payments
SET cash_box_id = @MainCashBoxId, bank_account_id = NULL, updated_at = GETDATE()
WHERE payment_method_id = 1 AND cash_box_id IS NULL;

UPDATE BenTaher_erp.dbo.supplier_payments
SET bank_account_id = @MainBankAccountId, cash_box_id = NULL, updated_at = GETDATE()
WHERE payment_method_id = 2 AND bank_account_id IS NULL;

UPDATE BenTaher_erp.dbo.expenses
SET cash_box_id = @MainCashBoxId, bank_account_id = NULL, updated_at = GETDATE()
WHERE payment_method = 1 AND cash_box_id IS NULL;

UPDATE BenTaher_erp.dbo.expenses
SET bank_account_id = @MainBankAccountId, cash_box_id = NULL, updated_at = GETDATE()
WHERE payment_method = 2 AND bank_account_id IS NULL;
