/*
| INS → ERP — step 09: surplus, wrong deductions, returns (suspended), stops
|
| User-confirmed mapping:
|   over_kst   → installment_surplus            (فائض)
|   over_kst_a → installment_surplus_archives   (فائض عقود مؤرشفة)
|   wrong_kst  → wrong_deductions               (خصومات واردة بالخطأ)
|   tar_kst    → installment_suspended          (ترجيع: من فائض / خطأ / قسط مخصوم)
|   stop_kst   → installment_stops              (عقد نشط)
|              → installment_stops_without_contract (عقد مؤرشف أو بدون عقد)
|
| tar_type → InstallmentReturnType:
|   1 = FromSurplus   (من الفائض)
|   2 = FromWrong     (من الخطأ)
|   3 = FromDeduction (من قسط مخصوم)
|
| IDs:
|   wrong_kst.wrong_no  → wrong_deductions.id
|   over_kst.wrec_no    → installment_surplus.id
|   over_kst_a.wrec_no  → installment_surplus_archives.id
|   tar_kst.wrec_no     → installment_suspended.id
|   stop_kst.rec_no     → installment_stops / installment_stops_without_contract.id
|
| Verify:
|   SELECT 'wrong' t, COUNT(*) c FROM BenTaher_erp.dbo.wrong_deductions
|   UNION ALL SELECT 'surplus', COUNT(*) FROM BenTaher_erp.dbo.installment_surplus
|   UNION ALL SELECT 'surplus_arc', COUNT(*) FROM BenTaher_erp.dbo.installment_surplus_archives
|   UNION ALL SELECT 'suspended', COUNT(*) FROM BenTaher_erp.dbo.installment_suspended
|   UNION ALL SELECT 'stops', COUNT(*) FROM BenTaher_erp.dbo.installment_stops
|   UNION ALL SELECT 'stops_no', COUNT(*) FROM BenTaher_erp.dbo.installment_stops_without_contract;
|   -- expect: 1326, 223, 3217, 3581, ~54, ~1387
*/

DECLARE @TargetCompany NVARCHAR(64) = N'BenTaher_erp';
DECLARE @FallbackUserId BIGINT = (
    SELECT MIN(id)
    FROM new_erp.dbo.users
    WHERE company = @TargetCompany
);

-- ---------------------------------------------------------------------------
-- 1) wrong_kst → wrong_deductions
-- ---------------------------------------------------------------------------
SET IDENTITY_INSERT BenTaher_erp.dbo.wrong_deductions ON;

INSERT INTO BenTaher_erp.dbo.wrong_deductions (
    id,
    payroll_bank_id,
    account_number,
    name,
    amount,
    deduction_date,
    batch_id,
    status,
    suspended_id,
    created_by,
    created_at,
    updated_at
)
SELECT
    CAST(w.wrong_no AS BIGINT),
    b.bank_tajmeeh,
    w.acc,
    w.name,
    ISNULL(w.kst, 0),
    w.tar_date,
    NULL,
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM BenTaher.dbo.tar_kst AS t
            WHERE t.tar_type = 2
              AND t.bank = w.bank
              AND t.acc = w.acc
              AND ABS(t.kst - w.kst) < 0.01
        )
            THEN 2
        ELSE 1
    END,
    NULL,
    COALESCE(u.id, @FallbackUserId),
    COALESCE(w.inp_date, w.tar_date, GETDATE()),
    COALESCE(w.inp_date, w.tar_date, GETDATE())
FROM BenTaher.dbo.wrong_kst AS w
LEFT JOIN BenTaher.dbo.bank AS b ON b.bank_no = w.bank
LEFT JOIN new_erp.dbo.users AS u
    ON u.company = @TargetCompany
    AND u.empno = w.emp
WHERE NOT EXISTS (
    SELECT 1
    FROM BenTaher_erp.dbo.wrong_deductions AS wd
    WHERE wd.id = w.wrong_no
);

SET IDENTITY_INSERT BenTaher_erp.dbo.wrong_deductions OFF;

-- Orphan returns (tar_kst type 2) with no matching wrong_kst source row
SET IDENTITY_INSERT BenTaher_erp.dbo.wrong_deductions ON;

INSERT INTO BenTaher_erp.dbo.wrong_deductions (
    id,
    payroll_bank_id,
    account_number,
    name,
    amount,
    deduction_date,
    batch_id,
    status,
    suspended_id,
    created_by,
    created_at,
    updated_at
)
SELECT
    900000 + CAST(t.wrec_no AS BIGINT),
    b.bank_tajmeeh,
    t.acc,
    t.name,
    ISNULL(t.kst, 0),
    t.tar_date,
    NULL,
    2,
    NULL,
    COALESCE(u.id, @FallbackUserId),
    COALESCE(t.inp_date, t.tar_date, GETDATE()),
    COALESCE(t.inp_date, t.tar_date, GETDATE())
FROM BenTaher.dbo.tar_kst AS t
LEFT JOIN BenTaher.dbo.bank AS b ON b.bank_no = t.bank
LEFT JOIN new_erp.dbo.users AS u
    ON u.company = @TargetCompany
    AND u.empno = t.emp
WHERE t.tar_type = 2
  AND NOT EXISTS (
      SELECT 1
      FROM BenTaher.dbo.wrong_kst AS w
      WHERE w.bank = t.bank
        AND w.acc = t.acc
        AND ABS(w.kst - t.kst) < 0.01
  )
  AND NOT EXISTS (
      SELECT 1
      FROM BenTaher_erp.dbo.wrong_deductions AS wd
      WHERE wd.id = 900000 + CAST(t.wrec_no AS BIGINT)
  );

SET IDENTITY_INSERT BenTaher_erp.dbo.wrong_deductions OFF;

-- ---------------------------------------------------------------------------
-- 2) over_kst → installment_surplus
-- ---------------------------------------------------------------------------
SET IDENTITY_INSERT BenTaher_erp.dbo.installment_surplus ON;

INSERT INTO BenTaher_erp.dbo.installment_surplus (
    id,
    contractable_type,
    contractable_id,
    surplus_date,
    amount,
    status,
    suspended_id,
    batch_id,
    deduction_id,
    created_by,
    created_at,
    updated_at
)
SELECT
    CAST(o.wrec_no AS BIGINT),
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM BenTaher_erp.dbo.installment_contracts AS c
            WHERE c.id = o.no
        )
            THEN N'installment_contract'
        ELSE N'installment_contract_archive'
    END,
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM BenTaher_erp.dbo.installment_contracts AS c
            WHERE c.id = o.no
        )
            THEN CAST(o.no AS BIGINT)
        ELSE (
            SELECT MIN(CAST(ma.id AS BIGINT))
            FROM BenTaher.dbo.MainArc AS ma
            WHERE ma.no = o.no
        )
    END,
    o.tar_date,
    ISNULL(o.kst, 0),
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM BenTaher.dbo.tar_kst AS t
            WHERE t.wrec_no = o.wrec_no
              AND t.tar_type = 1
        )
            THEN 2
        ELSE 1
    END,
    NULL,
    NULL,
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM BenTaher_erp.dbo.installment_deductions AS d
            WHERE d.id = o.wrec_no
        )
            THEN CAST(o.wrec_no AS BIGINT)
        ELSE NULL
    END,
    COALESCE(u.id, @FallbackUserId),
    COALESCE(o.inp_date, o.tar_date, GETDATE()),
    COALESCE(o.inp_date, o.tar_date, GETDATE())
FROM BenTaher.dbo.over_kst AS o
LEFT JOIN new_erp.dbo.users AS u
    ON u.company = @TargetCompany
    AND u.empno = o.emp
WHERE NOT EXISTS (
    SELECT 1
    FROM BenTaher_erp.dbo.installment_surplus AS s
    WHERE s.id = o.wrec_no
);

SET IDENTITY_INSERT BenTaher_erp.dbo.installment_surplus OFF;

-- ---------------------------------------------------------------------------
-- 3) tar_kst → installment_suspended (returns)
-- ---------------------------------------------------------------------------
SET IDENTITY_INSERT BenTaher_erp.dbo.installment_suspended ON;

INSERT INTO BenTaher_erp.dbo.installment_suspended (
    id,
    contractable_type,
    contractable_id,
    installment_contract_id,
    suspended_date,
    amount,
    status,
    batch_id,
    created_by,
    created_at,
    updated_at
)
SELECT
    CAST(t.wrec_no AS BIGINT),
    CASE t.tar_type
        WHEN 1 THEN
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM BenTaher_erp.dbo.installment_surplus AS s
                    WHERE s.id = t.wrec_no
                )
                    THEN N'installment_surplus'
                WHEN EXISTS (
                    SELECT 1
                    FROM BenTaher_erp.dbo.installment_contracts AS c
                    WHERE c.id = COALESCE(NULLIF(t.no, 0), k.no)
                )
                    THEN N'installment_contract'
                ELSE N'installment_contract_archive'
            END
        WHEN 2 THEN N'wrong_deduction'
        WHEN 3 THEN
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM BenTaher_erp.dbo.installment_contracts AS c
                    WHERE c.id = COALESCE(NULLIF(t.no, 0), k.no)
                )
                    THEN N'installment_contract'
                ELSE N'installment_contract_archive'
            END
        ELSE N'installment_contract'
    END,
    CASE t.tar_type
        WHEN 1 THEN
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM BenTaher_erp.dbo.installment_surplus AS s
                    WHERE s.id = t.wrec_no
                )
                    THEN CAST(t.wrec_no AS BIGINT)
                WHEN EXISTS (
                    SELECT 1
                    FROM BenTaher_erp.dbo.installment_contracts AS c
                    WHERE c.id = COALESCE(NULLIF(t.no, 0), k.no)
                )
                    THEN CAST(COALESCE(NULLIF(t.no, 0), k.no) AS BIGINT)
                ELSE COALESCE(
                    (
                        SELECT MIN(CAST(ma.id AS BIGINT))
                        FROM BenTaher.dbo.MainArc AS ma
                        WHERE ma.no = COALESCE(NULLIF(t.no, 0), k.no)
                    ),
                    CAST(NULLIF(t.no, 0) AS BIGINT),
                    CAST(k.no AS BIGINT),
                    CAST(t.wrec_no AS BIGINT)
                )
            END
        WHEN 2 THEN CAST(COALESCE(wm.wrong_no, 900000 + t.wrec_no) AS BIGINT)
        WHEN 3 THEN
            COALESCE(
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM BenTaher_erp.dbo.installment_contracts AS c
                        WHERE c.id = COALESCE(NULLIF(t.no, 0), k.no)
                    )
                        THEN CAST(COALESCE(NULLIF(t.no, 0), k.no) AS BIGINT)
                    ELSE (
                        SELECT MIN(CAST(ma.id AS BIGINT))
                        FROM BenTaher.dbo.MainArc AS ma
                        WHERE ma.no = COALESCE(NULLIF(t.no, 0), k.no)
                    )
                END,
                CAST(NULLIF(t.no, 0) AS BIGINT),
                CAST(k.no AS BIGINT),
                CAST(t.wrec_no AS BIGINT)
            )
        ELSE COALESCE(
            CAST(NULLIF(t.no, 0) AS BIGINT),
            CAST(k.no AS BIGINT),
            CAST(t.wrec_no AS BIGINT)
        )
    END,
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM BenTaher_erp.dbo.installment_contracts AS c
            WHERE c.id = COALESCE(NULLIF(t.no, 0), k.no)
        )
            THEN CAST(COALESCE(NULLIF(t.no, 0), k.no) AS BIGINT)
        ELSE NULL
    END,
    t.tar_date,
    ISNULL(t.kst, 0),
    CAST(t.tar_type AS TINYINT),
    NULL,
    COALESCE(u.id, @FallbackUserId),
    COALESCE(t.inp_date, t.tar_date, GETDATE()),
    COALESCE(t.inp_date, t.tar_date, GETDATE())
FROM BenTaher.dbo.tar_kst AS t
LEFT JOIN BenTaher.dbo.kst_trans AS k ON k.wrec_no = t.wrec_no
OUTER APPLY (
    SELECT TOP (1) w.wrong_no
    FROM BenTaher.dbo.wrong_kst AS w
    WHERE t.tar_type = 2
      AND w.bank = t.bank
      AND w.acc = t.acc
      AND ABS(w.kst - t.kst) < 0.01
    ORDER BY w.wrong_no
) AS wm
LEFT JOIN new_erp.dbo.users AS u
    ON u.company = @TargetCompany
    AND u.empno = t.emp
WHERE NOT EXISTS (
    SELECT 1
    FROM BenTaher_erp.dbo.installment_suspended AS s
    WHERE s.id = t.wrec_no
);

SET IDENTITY_INSERT BenTaher_erp.dbo.installment_suspended OFF;

-- Link surplus / wrong rows back to their return (suspended) record
UPDATE s
SET
    s.suspended_id = t.wrec_no,
    s.updated_at = GETDATE()
FROM BenTaher_erp.dbo.installment_surplus AS s
INNER JOIN BenTaher.dbo.tar_kst AS t
    ON t.wrec_no = s.id
    AND t.tar_type = 1;

UPDATE w
SET
    w.suspended_id = t.wrec_no,
    w.updated_at = GETDATE()
FROM BenTaher_erp.dbo.wrong_deductions AS w
INNER JOIN BenTaher.dbo.tar_kst AS t
    ON t.tar_type = 2
    AND (
        EXISTS (
            SELECT 1
            FROM BenTaher.dbo.wrong_kst AS wk
            WHERE wk.wrong_no = w.id
              AND wk.bank = t.bank
              AND wk.acc = t.acc
              AND ABS(wk.kst - t.kst) < 0.01
        )
        OR w.id = 900000 + CAST(t.wrec_no AS BIGINT)
    );

-- ---------------------------------------------------------------------------
-- 4) over_kst_a → installment_surplus_archives
-- ---------------------------------------------------------------------------
SET IDENTITY_INSERT BenTaher_erp.dbo.installment_surplus_archives ON;

INSERT INTO BenTaher_erp.dbo.installment_surplus_archives (
    id,
    installment_contract_id,
    surplus_date,
    amount,
    status,
    created_by,
    created_at,
    updated_at
)
SELECT
    CAST(o.wrec_no AS BIGINT),
    CAST(ma.id AS BIGINT),
    o.tar_date,
    ISNULL(o.kst, 0),
    1,
    COALESCE(u.id, @FallbackUserId),
    COALESCE(o.inp_date, o.tar_date, GETDATE()),
    COALESCE(o.inp_date, o.tar_date, GETDATE())
FROM BenTaher.dbo.over_kst_a AS o
INNER JOIN (
    SELECT no, MIN(id) AS id
    FROM BenTaher.dbo.MainArc
    GROUP BY no
) AS ma ON ma.no = o.no
LEFT JOIN new_erp.dbo.users AS u
    ON u.company = @TargetCompany
    AND u.empno = o.emp
WHERE EXISTS (
    SELECT 1
    FROM BenTaher_erp.dbo.installment_contract_archives AS ca
    WHERE ca.id = ma.id
)
  AND NOT EXISTS (
    SELECT 1
    FROM BenTaher_erp.dbo.installment_surplus_archives AS sa
    WHERE sa.id = o.wrec_no
);

SET IDENTITY_INSERT BenTaher_erp.dbo.installment_surplus_archives OFF;

-- ---------------------------------------------------------------------------
-- 5) stop_kst → installment_stops (active contracts)
-- ---------------------------------------------------------------------------
SET IDENTITY_INSERT BenTaher_erp.dbo.installment_stops ON;

INSERT INTO BenTaher_erp.dbo.installment_stops (
    id,
    installment_contract_id,
    stop_date,
    created_by,
    created_at,
    updated_at
)
SELECT
    CAST(s.rec_no AS BIGINT),
    CAST(s.no AS BIGINT),
    s.stop_date,
    COALESCE(u.id, @FallbackUserId),
    COALESCE(s.stop_date, GETDATE()),
    COALESCE(s.stop_date, GETDATE())
FROM BenTaher.dbo.stop_kst AS s
LEFT JOIN new_erp.dbo.users AS u
    ON u.company = @TargetCompany
    AND u.empno = s.emp
WHERE EXISTS (
    SELECT 1
    FROM BenTaher_erp.dbo.installment_contracts AS c
    WHERE c.id = s.no
)
  AND NOT EXISTS (
    SELECT 1
    FROM BenTaher_erp.dbo.installment_stops AS st
    WHERE st.id = s.rec_no
);

SET IDENTITY_INSERT BenTaher_erp.dbo.installment_stops OFF;

-- ---------------------------------------------------------------------------
-- 6) stop_kst → installment_stops_without_contract (archived / unmatched)
-- ---------------------------------------------------------------------------
SET IDENTITY_INSERT BenTaher_erp.dbo.installment_stops_without_contract ON;

INSERT INTO BenTaher_erp.dbo.installment_stops_without_contract (
    id,
    name,
    payroll_bank_id,
    account_number,
    stop_date,
    created_by,
    created_at,
    updated_at
)
SELECT
    CAST(s.rec_no AS BIGINT),
    ISNULL(NULLIF(LTRIM(RTRIM(s.name)), N''), ISNULL(s.acc, N'غير معروف')),
    b.bank_tajmeeh,
    s.acc,
    s.stop_date,
    COALESCE(u.id, @FallbackUserId),
    COALESCE(s.stop_date, GETDATE()),
    COALESCE(s.stop_date, GETDATE())
FROM BenTaher.dbo.stop_kst AS s
LEFT JOIN BenTaher.dbo.bank AS b ON b.bank_no = s.bank
LEFT JOIN new_erp.dbo.users AS u
    ON u.company = @TargetCompany
    AND u.empno = s.emp
WHERE NOT EXISTS (
    SELECT 1
    FROM BenTaher_erp.dbo.installment_contracts AS c
    WHERE c.id = s.no
)
  AND NOT EXISTS (
    SELECT 1
    FROM BenTaher_erp.dbo.installment_stops_without_contract AS sw
    WHERE sw.id = s.rec_no
);

SET IDENTITY_INSERT BenTaher_erp.dbo.installment_stops_without_contract OFF;

-- created_by fallback
UPDATE BenTaher_erp.dbo.wrong_deductions
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;

UPDATE BenTaher_erp.dbo.installment_surplus
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;

UPDATE BenTaher_erp.dbo.installment_suspended
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;

UPDATE BenTaher_erp.dbo.installment_surplus_archives
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;

UPDATE BenTaher_erp.dbo.installment_stops
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;

UPDATE BenTaher_erp.dbo.installment_stops_without_contract
SET created_by = @FallbackUserId, updated_at = GETDATE()
WHERE created_by IS NULL;
