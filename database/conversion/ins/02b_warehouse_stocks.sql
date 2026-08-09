/*
| INS → ERP conversion — step 02b: warehouse stocks (balances)
|
| Source : BenTaher.dbo.stores  → warehouse_id = st_no
|          BenTaher.dbo.halls   → warehouse_id = 10000 + hall_no
|
| Columns:
|   item_no  → item_id
|   raseed   → quantity_primary
|   quantity_secondary = 0  (INS stores/halls track primary unit only)
|
| Skips rows when item or warehouse missing in ERP.
| Idempotent: skips existing (warehouse_id, item_id) pairs.
|
| Verify:
|   SELECT COUNT(*) FROM BenTaher_erp.dbo.warehouse_stocks;
|   SELECT w.name, COUNT(*) items, SUM(ws.quantity_primary) qty
|   FROM BenTaher_erp.dbo.warehouse_stocks ws
|   JOIN BenTaher_erp.dbo.warehouses w ON w.id = ws.warehouse_id
|   GROUP BY w.name ORDER BY w.id;
|
| Sales warehouse mapping (already converted):
|   sells.sell_type = 1 → warehouse_id = place_no (store)
|   sells.sell_type = 2 → warehouse_id = 10000 + place_no (hall)
*/

INSERT INTO BenTaher_erp.dbo.warehouse_stocks (
    warehouse_id,
    item_id,
    quantity_primary,
    quantity_secondary,
    created_at,
    updated_at
)
SELECT
    s.st_no,
    s.item_no,
    ISNULL(s.raseed, 0),
    0,
    GETDATE(),
    GETDATE()
FROM BenTaher.dbo.stores AS s
WHERE EXISTS (SELECT 1 FROM BenTaher_erp.dbo.warehouses AS w WHERE w.id = s.st_no)
  AND EXISTS (SELECT 1 FROM BenTaher_erp.dbo.items AS i WHERE i.id = s.item_no)
  AND NOT EXISTS (
      SELECT 1
      FROM BenTaher_erp.dbo.warehouse_stocks AS ws
      WHERE ws.warehouse_id = s.st_no AND ws.item_id = s.item_no
  );

INSERT INTO BenTaher_erp.dbo.warehouse_stocks (
    warehouse_id,
    item_id,
    quantity_primary,
    quantity_secondary,
    created_at,
    updated_at
)
SELECT
    10000 + h.hall_no,
    h.item_no,
    ISNULL(h.raseed, 0),
    0,
    GETDATE(),
    GETDATE()
FROM BenTaher.dbo.halls AS h
WHERE EXISTS (SELECT 1 FROM BenTaher_erp.dbo.warehouses AS w WHERE w.id = 10000 + h.hall_no)
  AND EXISTS (SELECT 1 FROM BenTaher_erp.dbo.items AS i WHERE i.id = h.item_no)
  AND NOT EXISTS (
      SELECT 1
      FROM BenTaher_erp.dbo.warehouse_stocks AS ws
      WHERE ws.warehouse_id = 10000 + h.hall_no AND ws.item_id = h.item_no
  );
