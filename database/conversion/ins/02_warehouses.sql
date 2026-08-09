/*
| INS → ERP conversion — step 02: warehouses (master)
|
| Source : BenTaher.dbo.stores_names  → ERP warehouses (storage,  warehouse_type = 0)
|          BenTaher.dbo.halls_names   → ERP warehouses (showroom, warehouse_type = 1)
|
| Id rules:
|   stores : id = st_no
|   halls  : id = 10000 + hall_no   (avoid collision with store ids)
|
| NOT warehouses: BenTaher.dbo.place → workplaces (separate step)
|
| Verify:
|   SELECT id, name, warehouse_type, is_active
|   FROM BenTaher_erp.dbo.warehouses ORDER BY id;
*/

DECLARE @LegacyDb NVARCHAR(128) = N'BenTaher';
DECLARE @TargetDb NVARCHAR(128) = N'BenTaher_erp';

-- Storage warehouses (stores_names)
SET IDENTITY_INSERT BenTaher_erp.dbo.warehouses ON;

INSERT INTO BenTaher_erp.dbo.warehouses (id, name, warehouse_type, is_active, created_at, updated_at)
SELECT
    s.st_no,
    s.st_name,
    0,
    1,
    GETDATE(),
    GETDATE()
FROM BenTaher.dbo.stores_names AS s
WHERE NOT EXISTS (
    SELECT 1 FROM BenTaher_erp.dbo.warehouses AS w WHERE w.id = s.st_no
);

-- Showroom warehouses (halls_names)
INSERT INTO BenTaher_erp.dbo.warehouses (id, name, warehouse_type, is_active, created_at, updated_at)
SELECT
    10000 + h.hall_no,
    h.hall_name,
    1,
    1,
    GETDATE(),
    GETDATE()
FROM BenTaher.dbo.halls_names AS h
WHERE NOT EXISTS (
    SELECT 1 FROM BenTaher_erp.dbo.warehouses AS w WHERE w.id = 10000 + h.hall_no
);

SET IDENTITY_INSERT BenTaher_erp.dbo.warehouses OFF;

-- Sync names if re-run after manual edits
UPDATE w
SET w.name = s.st_name, w.updated_at = GETDATE()
FROM BenTaher_erp.dbo.warehouses AS w
INNER JOIN BenTaher.dbo.stores_names AS s ON s.st_no = w.id
WHERE w.warehouse_type = 0
  AND w.name COLLATE DATABASE_DEFAULT <> s.st_name COLLATE DATABASE_DEFAULT;

UPDATE w
SET w.name = h.hall_name, w.updated_at = GETDATE()
FROM BenTaher_erp.dbo.warehouses AS w
INNER JOIN BenTaher.dbo.halls_names AS h ON 10000 + h.hall_no = w.id
WHERE w.warehouse_type = 1
  AND w.name COLLATE DATABASE_DEFAULT <> h.hall_name COLLATE DATABASE_DEFAULT;
