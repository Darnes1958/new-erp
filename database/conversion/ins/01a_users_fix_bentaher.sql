/*
| Fix BenTaher users after partial PHP import:
| - Backfill old_user_id + empno on existing rows (matched by email)
| - Insert missing legacy users (duplicate emails → append legacy id)
*/

DECLARE @LegacyCompany NVARCHAR(64) = N'BenTaher';
DECLARE @TargetCompany NVARCHAR(64) = N'BenTaher_erp';

-- 1) Backfill mapping columns on users already imported (same email, one legacy row each)
UPDATE eu
SET
    eu.old_user_id = lu.id,
    eu.empno = lu.empno,
    eu.updated_at = GETDATE()
FROM new_erp.dbo.users AS eu
INNER JOIN useradmin.dbo.users AS lu
    ON lu.company = @LegacyCompany
    AND LOWER(LTRIM(RTRIM(lu.email))) = LOWER(LTRIM(RTRIM(eu.email)))
WHERE eu.company = @TargetCompany
  AND eu.old_user_id IS NULL
  AND lu.id IN (
      SELECT MIN(x.id)
      FROM useradmin.dbo.users AS x
      WHERE x.company = @LegacyCompany
      GROUP BY LOWER(LTRIM(RTRIM(x.email)))
  );

-- 2) Insert legacy users still missing
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
    1,
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
      WHERE eu.company = @TargetCompany
        AND eu.old_user_id = lu.id
  )
  AND NOT EXISTS (
      SELECT 1
      FROM new_erp.dbo.users AS eu
      WHERE eu.company = @TargetCompany
        AND eu.empno = lu.empno
  );
