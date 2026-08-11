/*
| INS → ERP conversion — step 01: users
|
| Source : useradmin.dbo.users
| Target : new_erp.dbo.users
|
| Parameters (edit before run):
|   @LegacyCompany  — INS company key in useradmin (e.g. BenTaher)
|   @TargetCompany  — ERP connection name (e.g. BenTaher_erp)
|
| Rules:
|   - id          → NEW (identity, do not reuse legacy id)
|   - old_user_id → legacy useradmin.users.id
|   - empno       → legacy empno (join key for company tables .emp → created_by)
|   - company     → @TargetCompany
|
| Email was not meaningful in INS — duplicate emails get legacy id appended
| (e.g. nuri@yahoo.com → nuri10020@yahoo.com) to satisfy unique index.
|
| status: 0 when useradmin.bans has an active ban (deleted_at IS NULL and not
| expired) or users.banned_at IS NOT NULL; otherwise 1.
|
| Verify:
|   SELECT id, old_user_id, empno, company, name, email
|   FROM new_erp.dbo.users WHERE company = @TargetCompany ORDER BY empno;
|
| Roles: run 01b_user_roles.sql after this step.
*/

DECLARE @LegacyCompany NVARCHAR(64) = N'BenTaher';
DECLARE @TargetCompany NVARCHAR(64) = N'BenTaher_erp';

INSERT INTO new_erp.dbo.users (
    name,
    email,
    email_verified_at,
    password,
    company,
    warehouse_id,
    status,
    is_prog,
    remember_token,
    created_at,
    updated_at,
    empno,
    old_user_id
)
SELECT
    lu.name,
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM new_erp.dbo.users AS x
            WHERE LOWER(LTRIM(RTRIM(x.email))) = LOWER(LTRIM(RTRIM(lu.email)))
        )
            THEN CONCAT(
                LEFT(lu.email, NULLIF(CHARINDEX('@', lu.email), 0) - 1),
                CAST(lu.id AS NVARCHAR(20)),
                SUBSTRING(lu.email, CHARINDEX('@', lu.email), LEN(lu.email))
            )
        ELSE lu.email
    END,
    lu.email_verified_at,
    lu.password,
    @TargetCompany,
    NULL,
    CASE
        WHEN lu.banned_at IS NOT NULL
            OR EXISTS (
                SELECT 1
                FROM useradmin.dbo.bans AS b
                WHERE b.bannable_type = N'App\Models\User'
                  AND b.bannable_id = lu.id
                  AND b.deleted_at IS NULL
                  AND (b.expired_at IS NULL OR b.expired_at > GETDATE())
            )
            THEN 0
        ELSE 1
    END,
    0,
    lu.remember_token,
    lu.created_at,
    lu.updated_at,
    lu.empno,
    lu.id
FROM useradmin.dbo.users AS lu
WHERE lu.company = @LegacyCompany
  AND NOT EXISTS (
      SELECT 1
      FROM new_erp.dbo.users AS eu
      WHERE eu.old_user_id = lu.id
        AND eu.company = @TargetCompany
  );

-- created_by mapping (later steps) — always via empno, not old_user_id:
-- UPDATE si SET created_by = u.id
-- FROM BenTaher_erp.dbo.sales_invoices AS si
-- INNER JOIN BenTaher.dbo.sells AS s ON si.id = s.order_no
-- INNER JOIN new_erp.dbo.users AS u
--     ON u.company = @TargetCompany AND u.empno = s.emp;
