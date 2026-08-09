/*
| INS → ERP conversion — step 02: master (jeha → customers / suppliers)
|
| Source : BenTaher.dbo.jeha + jeha_type
|
| jeha_type = 2  → suppliers
| jeha_type <> 2 → customers (customer_type_id = jeha_type)
|
| Verify:
|   SELECT COUNT(*) FROM BenTaher_erp.dbo.customers;
|   SELECT COUNT(*) FROM BenTaher_erp.dbo.suppliers;
|   SELECT jeha_type, COUNT(*) FROM BenTaher.dbo.jeha GROUP BY jeha_type;
|
| Run 05_default_cash_accounts.sql after payment-related steps.
*/

DECLARE @TargetCompany NVARCHAR(64) = N'BenTaher_erp';

-- customer_types from jeha_type (reference only — not jeha_type=2 for customers)
SET IDENTITY_INSERT BenTaher_erp.dbo.customer_types ON;

INSERT INTO BenTaher_erp.dbo.customer_types (id, name, created_at, updated_at)
SELECT jt.type_no, jt.type_name, GETDATE(), GETDATE()
FROM BenTaher.dbo.jeha_type AS jt
WHERE NOT EXISTS (
    SELECT 1 FROM BenTaher_erp.dbo.customer_types AS ct WHERE ct.id = jt.type_no
);

SET IDENTITY_INSERT BenTaher_erp.dbo.customer_types OFF;

-- customers (jeha_type <> 2)
SET IDENTITY_INSERT BenTaher_erp.dbo.customers ON;

INSERT INTO BenTaher_erp.dbo.customers (
    id, name, address, mdar, libyana, card_no, others, customer_type_id, created_by, created_at, updated_at
)
SELECT
    j.jeha_no,
    j.jeha_name,
    j.address,
    j.mdar,
    j.libyana,
    j.acc_no,
    j.others,
    j.jeha_type,
    NULL,
    GETDATE(),
    GETDATE()
FROM BenTaher.dbo.jeha AS j
WHERE ISNULL(j.jeha_type, 1) <> 2
  AND NOT EXISTS (
      SELECT 1 FROM BenTaher_erp.dbo.customers AS c WHERE c.id = j.jeha_no
  );

SET IDENTITY_INSERT BenTaher_erp.dbo.customers OFF;

-- suppliers (jeha_type = 2)
SET IDENTITY_INSERT BenTaher_erp.dbo.suppliers ON;

INSERT INTO BenTaher_erp.dbo.suppliers (
    id, name, address, mdar, libyana, card_no, others, created_by, created_at, updated_at
)
SELECT
    j.jeha_no,
    j.jeha_name,
    j.address,
    j.mdar,
    j.libyana,
    j.acc_no,
    j.others,
    NULL,
    GETDATE(),
    GETDATE()
FROM BenTaher.dbo.jeha AS j
WHERE j.jeha_type = 2
  AND NOT EXISTS (
      SELECT 1 FROM BenTaher_erp.dbo.suppliers AS s WHERE s.id = j.jeha_no
  );

SET IDENTITY_INSERT BenTaher_erp.dbo.suppliers OFF;
