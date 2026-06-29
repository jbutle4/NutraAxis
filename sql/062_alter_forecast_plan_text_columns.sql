/*
  NutraAxis Operations — convert legacy TEXT columns that break forecast plan queries
  (SQL Server error 402: nvarchar and text are incompatible in the equal to operator)
*/

DECLARE @sql NVARCHAR(MAX);

DECLARE col_cursor CURSOR LOCAL FAST_FORWARD FOR
SELECT
    N'ALTER TABLE ' + QUOTENAME(OBJECT_SCHEMA_NAME(c.object_id)) + N'.' + QUOTENAME(OBJECT_NAME(c.object_id))
    + N' ALTER COLUMN ' + QUOTENAME(c.name) + N' NVARCHAR(200) '
    + CASE WHEN c.is_nullable = 1 THEN N'NULL' ELSE N'NOT NULL' END + N';'
FROM sys.columns c
INNER JOIN sys.types t ON c.user_type_id = t.user_type_id
WHERE t.name IN (N'text', N'ntext')
  AND (
        (OBJECT_NAME(c.object_id) = N'POLineItem' AND c.name = N'ItemSKU')
     OR (OBJECT_NAME(c.object_id) = N'PORDetail' AND c.name = N'ItemSKU')
     OR (OBJECT_NAME(c.object_id) = N'PurchaseOrder' AND c.name = N'POStatus')
     OR (OBJECT_NAME(c.object_id) = N'POReceipt' AND c.name = N'PORStatus')
     OR (OBJECT_NAME(c.object_id) = N'MonthlySalesSummary' AND c.name = N'SKU')
     OR (OBJECT_NAME(c.object_id) = N'InventoryBalance' AND c.name = N'SKU')
     OR (OBJECT_NAME(c.object_id) = N'ForecastPlan' AND c.name = N'SKU')
  );

OPEN col_cursor;
FETCH NEXT FROM col_cursor INTO @sql;

WHILE @@FETCH_STATUS = 0
BEGIN
    EXEC sp_executesql @sql;
    FETCH NEXT FROM col_cursor INTO @sql;
END;

CLOSE col_cursor;
DEALLOCATE col_cursor;
GO
