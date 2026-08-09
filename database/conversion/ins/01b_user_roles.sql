/*
| INS → ERP conversion — step 01b: user roles
|
| Run after 01_users.sql. Maps legacy model_has_roles to new user ids via old_user_id.
|
| Parameters: same as 01_users.sql
*/

DECLARE @LegacyCompany NVARCHAR(64) = N'BenTaher';
DECLARE @TargetCompany NVARCHAR(64) = N'BenTaher_erp';

INSERT INTO new_erp.dbo.model_has_roles (role_id, model_type, model_id)
SELECT
    mhr.role_id,
    N'App\Models\User',
    eu.id
FROM useradmin.dbo.model_has_roles AS mhr
INNER JOIN new_erp.dbo.users AS eu
    ON eu.old_user_id = mhr.model_id
    AND eu.company = @TargetCompany
WHERE mhr.model_type = N'App\Models\User'
  AND EXISTS (
      SELECT 1
      FROM useradmin.dbo.users AS lu
      WHERE lu.id = mhr.model_id
        AND lu.company = @LegacyCompany
  )
  AND EXISTS (
      SELECT 1
      FROM new_erp.dbo.roles AS r
      WHERE r.id = mhr.role_id
  )
  AND NOT EXISTS (
      SELECT 1
      FROM new_erp.dbo.model_has_roles AS x
      WHERE x.role_id = mhr.role_id
        AND x.model_type = N'App\Models\User'
        AND x.model_id = eu.id
  );
