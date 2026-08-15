/*
  Per-SKU QuickBooks tracking mode: Inventory (qty + asset) vs NonInventory (expense-only).
  NULL-safe default Inventory matches existing env-wide QBO_SKU_ITEM_TYPE=Inventory behavior.
*/

IF COL_LENGTH('dbo.SKUMaster', 'QBO_TrackingMode') IS NULL
BEGIN
    ALTER TABLE dbo.SKUMaster
    ADD QBO_TrackingMode NVARCHAR(20) NOT NULL
        CONSTRAINT DF_SKUMaster_QBO_TrackingMode DEFAULT (N'Inventory');
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = N'CK_SKUMaster_QBO_TrackingMode'
)
BEGIN
    ALTER TABLE dbo.SKUMaster
    ADD CONSTRAINT CK_SKUMaster_QBO_TrackingMode
        CHECK (QBO_TrackingMode IN (N'Inventory', N'NonInventory'));
END
GO
