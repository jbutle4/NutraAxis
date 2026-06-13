/*
  NutraAxis Operations — LineTotal persisted computed column breaks PDO inserts
  on Azure (ANSI_NULLS session options). Use non-persisted computed instead.
*/

IF EXISTS (
    SELECT 1
    FROM sys.computed_columns
    WHERE object_id = OBJECT_ID(N'dbo.POLineItem')
      AND name = N'LineTotal'
      AND is_persisted = 1
)
BEGIN
    ALTER TABLE dbo.POLineItem DROP COLUMN LineTotal;
    ALTER TABLE dbo.POLineItem ADD LineTotal AS (Quantity * UnitPrice);
END;
GO
