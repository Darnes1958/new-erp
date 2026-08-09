/*
| INS → ERP — step 11: installment cheque deliveries (تسليم الشيكات)
|
| Source:
|   chk_tasleem → installment_cheques
|
| Mapping:
|   rec_no     → id
|   no         → installment_contract_id (active contracts only)
|   chk_count  → cheque_count
|   wdate      → cheque_date
|   emp        → created_by
|
| Note:
|   main.chk_in / chk_out are aggregate counts — already on installment_contracts
|   (cheques_in / cheques_out) from step installments import.
|
|   chk_tasleem rows for archived/deleted contracts (MainArc / missing main)
|   are skipped: ERP removes installment_cheques when a contract is archived.
|
| Verify:
|   SELECT COUNT(*) FROM BenTaher_erp.dbo.installment_cheques;  -- expect ~21
|   SELECT SUM(cheques_in), SUM(cheques_out) FROM BenTaher_erp.dbo.installment_contracts;
*/

DECLARE @TargetCompany NVARCHAR(64) = N'BenTaher_erp';
DECLARE @FallbackUserId BIGINT = (
    SELECT MIN(id)
    FROM new_erp.dbo.users
    WHERE company = @TargetCompany
);

SET IDENTITY_INSERT BenTaher_erp.dbo.installment_cheques ON;

INSERT INTO BenTaher_erp.dbo.installment_cheques (
    id,
    installment_contract_id,
    cheque_count,
    cheque_date,
    created_by,
    created_at,
    updated_at
)
SELECT
    CAST(ct.rec_no AS BIGINT),
    CAST(ct.no AS BIGINT),
    CAST(ISNULL(ct.chk_count, 0) AS INT),
    ct.wdate,
    COALESCE(u.id, @FallbackUserId),
    COALESCE(ct.wdate, GETDATE()),
    COALESCE(ct.wdate, GETDATE())
FROM BenTaher.dbo.chk_tasleem AS ct
INNER JOIN BenTaher_erp.dbo.installment_contracts AS c
    ON c.id = ct.no
LEFT JOIN new_erp.dbo.users AS u
    ON u.company = @TargetCompany
    AND u.empno = ct.emp
WHERE ISNULL(ct.chk_count, 0) >= 0
  AND NOT EXISTS (
      SELECT 1
      FROM BenTaher_erp.dbo.installment_cheques AS ic
      WHERE ic.id = ct.rec_no
  );

SET IDENTITY_INSERT BenTaher_erp.dbo.installment_cheques OFF;

UPDATE BenTaher_erp.dbo.installment_cheques
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;
