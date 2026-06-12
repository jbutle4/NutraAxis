/*
  NutraAxis Operations — link SKUMaster to Supplier
*/

IF COL_LENGTH('dbo.SKUMaster', 'SupplierID') IS NULL
    ALTER TABLE dbo.SKUMaster ADD SupplierID INT NULL;
GO

IF OBJECT_ID(N'dbo.FK_SKUMaster_Supplier', N'F') IS NULL
BEGIN
    ALTER TABLE dbo.SKUMaster
    ADD CONSTRAINT FK_SKUMaster_Supplier
        FOREIGN KEY (SupplierID) REFERENCES dbo.Supplier (SupplierID);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = N'IX_SKUMaster_SupplierID'
      AND object_id = OBJECT_ID(N'dbo.SKUMaster')
)
    CREATE NONCLUSTERED INDEX IX_SKUMaster_SupplierID
        ON dbo.SKUMaster (SupplierID)
        WHERE SupplierID IS NOT NULL;
GO
