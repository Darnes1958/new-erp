/*
| INS → ERP — step 10: deduction batches (حوافظ الأقساط)
|
| Source:
|   hafitha       → deduction_batches
|   hafitha_tran  → deduction_batch_lines   (INS name; not hafitha_trans)
|
| Header mapping (hafitha):
|   hafitha_no    → id
|   bank          → installment_bank_id
|   bank_tajmeeh  → payroll_bank_id (via bank table)
|   hafitha_date  → batch_date
|   line dates    → from_date / to_date (MIN/MAX ksm_date in hafitha_tran)
|   hafitha_state → status (1 = Posted)
|   hafitha_tot   → total_amount
|   kst_morahel   → posted_normal_amount
|   kst_over      → posted_surplus_amount
|   kst_half_over → posted_partial_amount
|   kst_wrong     → wrong_amount
|
| Line mapping (hafitha_tran):
|   rec_no        → id
|   hafitha       → deduction_batch_id
|   no            → contractable (installment_contract / installment_contract_archive)
|   acc           → account_number
|   kst           → amount
|   ksm_date      → deduction_date
|   name          → notes (when wrong / no contract)
|   kst_type      → entry_type + posted_type
|
| INS kst_type → ERP:
|   1 قبض        → Active/Archive + Normal
|   2 مرتجع      → Archive + Normal
|   3 فائض       → Active/Archive + Surplus
|   4 موقوف/خطأ  → Wrong + Wrong (contractable: wrong_deduction)
|   5 مرتجع موقوف→ Active/Archive + Normal
|
| After import, backfill batch_id on rows that reference h_no / hafitha.
|
| Verify:
|   SELECT COUNT(*) FROM BenTaher_erp.dbo.deduction_batches;           -- 634
|   SELECT COUNT(*) FROM BenTaher_erp.dbo.deduction_batch_lines;       -- 8019
|   SELECT COUNT(*) FROM BenTaher_erp.dbo.installment_deductions WHERE batch_id IS NOT NULL;
*/

DECLARE @TargetCompany NVARCHAR(64) = N'BenTaher_erp';
DECLARE @FallbackUserId BIGINT = (
    SELECT MIN(id)
    FROM new_erp.dbo.users
    WHERE company = @TargetCompany
);

-- ---------------------------------------------------------------------------
-- 1) hafitha → deduction_batches
-- ---------------------------------------------------------------------------
SET IDENTITY_INSERT BenTaher_erp.dbo.deduction_batches ON;

INSERT INTO BenTaher_erp.dbo.deduction_batches (
    id,
    payroll_bank_id,
    installment_bank_id,
    status,
    batch_date,
    from_date,
    to_date,
    total_amount,
    posted_normal_amount,
    posted_archive_amount,
    posted_surplus_amount,
    posted_partial_amount,
    wrong_amount,
    posted_cancelled_amount,
    notes,
    created_by,
    created_at,
    updated_at
)
SELECT
    CAST(h.hafitha_no AS BIGINT),
    b.bank_tajmeeh,
    CAST(h.bank AS BIGINT),
    CAST(ISNULL(h.hafitha_state, 0) AS TINYINT),
    h.hafitha_date,
    COALESCE(rng.from_date, h.hafitha_date),
    COALESCE(rng.to_date, h.hafitha_date),
    ISNULL(h.hafitha_tot, 0),
    ISNULL(h.kst_morahel, 0),
    0,
    ISNULL(h.kst_over, 0),
    ISNULL(h.kst_half_over, 0),
    ISNULL(h.kst_wrong, 0),
    0,
    NULL,
    @FallbackUserId,
    COALESCE(h.hafitha_date, GETDATE()),
    COALESCE(h.hafitha_date, GETDATE())
FROM BenTaher.dbo.hafitha AS h
LEFT JOIN BenTaher.dbo.bank AS b ON b.bank_no = h.bank
OUTER APPLY (
    SELECT
        MIN(ht.ksm_date) AS from_date,
        MAX(ht.ksm_date) AS to_date
    FROM BenTaher.dbo.hafitha_tran AS ht
    WHERE ht.hafitha = h.hafitha_no
) AS rng
WHERE NOT EXISTS (
    SELECT 1
    FROM BenTaher_erp.dbo.deduction_batches AS db
    WHERE db.id = h.hafitha_no
);

SET IDENTITY_INSERT BenTaher_erp.dbo.deduction_batches OFF;

-- ---------------------------------------------------------------------------
-- 2) hafitha_tran → deduction_batch_lines
-- ---------------------------------------------------------------------------
SET IDENTITY_INSERT BenTaher_erp.dbo.deduction_batch_lines ON;

INSERT INTO BenTaher_erp.dbo.deduction_batch_lines (
    id,
    deduction_batch_id,
    contractable_type,
    contractable_id,
    account_number,
    amount,
    deduction_date,
    notes,
    entry_type,
    posted_type,
    created_by,
    created_at,
    updated_at
)
SELECT
    CAST(ht.rec_no AS BIGINT),
    CAST(ht.hafitha AS BIGINT),
    CASE
        WHEN ht.kst_type = 4 OR ISNULL(ht.no, 0) = 0
            THEN N'wrong_deduction'
        WHEN EXISTS (
            SELECT 1
            FROM BenTaher_erp.dbo.installment_contracts AS c
            WHERE c.id = ht.no
        )
            THEN N'installment_contract'
        ELSE N'installment_contract_archive'
    END,
    CASE
        WHEN ht.kst_type = 4 OR ISNULL(ht.no, 0) = 0
            THEN CAST(COALESCE(wm.wrong_id, 0) AS BIGINT)
        WHEN EXISTS (
            SELECT 1
            FROM BenTaher_erp.dbo.installment_contracts AS c
            WHERE c.id = ht.no
        )
            THEN CAST(ht.no AS BIGINT)
        ELSE COALESCE(
            (
                SELECT MIN(CAST(ma.id AS BIGINT))
                FROM BenTaher.dbo.MainArc AS ma
                WHERE ma.no = ht.no
            ),
            CAST(NULLIF(ht.no, 0) AS BIGINT),
            CAST(0 AS BIGINT)
        )
    END,
    ht.acc,
    ISNULL(ht.kst, 0),
    ht.ksm_date,
    CASE
        WHEN ht.kst_type IN (4, 5) OR ISNULL(ht.no, 0) = 0
            THEN ht.name
        ELSE NULL
    END,
    CAST(
        CASE ht.kst_type
            WHEN 1 THEN
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM BenTaher_erp.dbo.installment_contracts AS c
                        WHERE c.id = ht.no
                    )
                        THEN 1
                    ELSE 2
                END
            WHEN 2 THEN 2
            WHEN 3 THEN
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM BenTaher_erp.dbo.installment_contracts AS c
                        WHERE c.id = ht.no
                    )
                        THEN 1
                    ELSE 2
                END
            WHEN 4 THEN 5
            WHEN 5 THEN
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM BenTaher_erp.dbo.installment_contracts AS c
                        WHERE c.id = ht.no
                    )
                        THEN 1
                    ELSE 2
                END
            ELSE 1
        END AS TINYINT
    ),
    CAST(
        CASE ht.kst_type
            WHEN 3 THEN 3
            WHEN 4 THEN 5
            ELSE 1
        END AS TINYINT
    ),
    COALESCE(u.id, @FallbackUserId),
    COALESCE(ht.ksm_date, GETDATE()),
    COALESCE(ht.ksm_date, GETDATE())
FROM BenTaher.dbo.hafitha_tran AS ht
LEFT JOIN new_erp.dbo.users AS u
    ON u.company = @TargetCompany
    AND u.empno = ht.emp
OUTER APPLY (
    SELECT TOP (1) wd.id AS wrong_id
    FROM BenTaher_erp.dbo.wrong_deductions AS wd
    WHERE ht.kst_type = 4
      AND wd.account_number COLLATE DATABASE_DEFAULT = ht.acc COLLATE DATABASE_DEFAULT
      AND ABS(wd.amount - ISNULL(ht.kst, 0)) < 0.01
    ORDER BY wd.id
) AS wm
WHERE EXISTS (
    SELECT 1
    FROM BenTaher_erp.dbo.deduction_batches AS db
    WHERE db.id = ht.hafitha
)
  AND NOT EXISTS (
    SELECT 1
    FROM BenTaher_erp.dbo.deduction_batch_lines AS dbl
    WHERE dbl.id = ht.rec_no
);

SET IDENTITY_INSERT BenTaher_erp.dbo.deduction_batch_lines OFF;

-- ---------------------------------------------------------------------------
-- 3) Backfill batch_id on related installment rows (h_no / hafitha)
-- ---------------------------------------------------------------------------
UPDATE d
SET
    d.batch_id = NULL,
    d.updated_at = GETDATE()
FROM BenTaher_erp.dbo.installment_deductions AS d
WHERE d.batch_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM BenTaher_erp.dbo.deduction_batches AS db
      WHERE db.id = d.batch_id
  );

UPDATE d
SET
    d.batch_id = k.h_no,
    d.updated_at = GETDATE()
FROM BenTaher_erp.dbo.installment_deductions AS d
INNER JOIN BenTaher.dbo.kst_trans AS k ON k.wrec_no = d.id
INNER JOIN BenTaher_erp.dbo.deduction_batches AS db ON db.id = k.h_no
WHERE ISNULL(k.h_no, 0) > 0
  AND (d.batch_id IS NULL OR d.batch_id <> k.h_no);

UPDATE w
SET
    w.batch_id = wk.h_no,
    w.updated_at = GETDATE()
FROM BenTaher_erp.dbo.wrong_deductions AS w
INNER JOIN BenTaher.dbo.wrong_kst AS wk ON wk.wrong_no = w.id
INNER JOIN BenTaher_erp.dbo.deduction_batches AS db ON db.id = wk.h_no
WHERE ISNULL(wk.h_no, 0) > 0
  AND w.batch_id IS NULL;

UPDATE s
SET
    s.batch_id = o.h_no,
    s.updated_at = GETDATE()
FROM BenTaher_erp.dbo.installment_surplus AS s
INNER JOIN BenTaher.dbo.over_kst AS o ON o.wrec_no = s.id
INNER JOIN BenTaher_erp.dbo.deduction_batches AS db ON db.id = o.h_no
WHERE ISNULL(o.h_no, 0) > 0
  AND s.batch_id IS NULL;

UPDATE sus
SET
    sus.batch_id = db.id,
    sus.updated_at = GETDATE()
FROM BenTaher_erp.dbo.installment_suspended AS sus
INNER JOIN BenTaher.dbo.tar_kst AS t ON t.wrec_no = sus.id
INNER JOIN BenTaher.dbo.hafitha_tran AS ht
    ON ht.acc COLLATE DATABASE_DEFAULT = t.acc COLLATE DATABASE_DEFAULT
    AND ABS(ht.kst - t.kst) < 0.01
    AND ht.ksm_date = t.tar_date
INNER JOIN BenTaher_erp.dbo.deduction_batches AS db ON db.id = ht.hafitha
WHERE sus.batch_id IS NULL;

UPDATE BenTaher_erp.dbo.deduction_batches
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;

UPDATE BenTaher_erp.dbo.deduction_batch_lines
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;
