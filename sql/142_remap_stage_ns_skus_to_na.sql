/*
  Remap legacy NutraSync NS-* SKUs on Stage ACCS order lines to canonical NA-* SKUMaster codes.
  Only updates lines where the NA twin exists in SKUMaster.
*/

DECLARE @Remapped INT = 0;

UPDATE d
SET d.SKU = na.SKUCode
FROM dbo.AccsSalesOrderDetail d
INNER JOIN dbo.AccsSalesOrderHeader h
  ON h.AccsSalesOrderHeaderID = d.AccsSalesOrderHeaderID
INNER JOIN dbo.SKUMaster na
  ON na.SKUCode = N'NA-' + SUBSTRING(d.SKU, 4, 97)
WHERE h.SourceEnvironment = N'stage'
  AND d.SKU LIKE N'NS-%'
  AND LEN(d.SKU) > 3;

SET @Remapped = @@ROWCOUNT;

SELECT @Remapped AS stage_order_lines_remapped;
