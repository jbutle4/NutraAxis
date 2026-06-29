/*
  NutraAxis Operations — rename JazzInventorySnapshot to InventoryBalance
*/

IF OBJECT_ID(N'dbo.JazzInventorySnapshot', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.InventoryBalance', N'U') IS NULL
BEGIN
    EXEC sp_rename N'dbo.JazzInventorySnapshot', N'InventoryBalance', N'OBJECT';

    IF OBJECT_ID(N'dbo.PK_JazzInventorySnapshot', N'PK') IS NOT NULL
        EXEC sp_rename N'dbo.PK_JazzInventorySnapshot', N'PK_InventoryBalance', N'OBJECT';

    IF OBJECT_ID(N'dbo.DF_JazzInventorySnapshot_Available', N'D') IS NOT NULL
        EXEC sp_rename N'dbo.DF_JazzInventorySnapshot_Available', N'DF_InventoryBalance_Available', N'OBJECT';

    IF OBJECT_ID(N'dbo.DF_JazzInventorySnapshot_OnHand', N'D') IS NOT NULL
        EXEC sp_rename N'dbo.DF_JazzInventorySnapshot_OnHand', N'DF_InventoryBalance_OnHand', N'OBJECT';

    IF OBJECT_ID(N'dbo.DF_JazzInventorySnapshot_Ordered', N'D') IS NOT NULL
        EXEC sp_rename N'dbo.DF_JazzInventorySnapshot_Ordered', N'DF_InventoryBalance_Ordered', N'OBJECT';

    IF OBJECT_ID(N'dbo.DF_JazzInventorySnapshot_Total', N'D') IS NOT NULL
        EXEC sp_rename N'dbo.DF_JazzInventorySnapshot_Total', N'DF_InventoryBalance_Total', N'OBJECT';

    IF EXISTS (
        SELECT 1
        FROM sys.indexes
        WHERE name = N'IX_JazzInventorySnapshot_SnapshotDateTime'
          AND object_id = OBJECT_ID(N'dbo.InventoryBalance')
    )
        EXEC sp_rename N'dbo.InventoryBalance.IX_JazzInventorySnapshot_SnapshotDateTime', N'IX_InventoryBalance_SnapshotDateTime', N'INDEX';

    IF EXISTS (
        SELECT 1
        FROM sys.indexes
        WHERE name = N'IX_JazzInventorySnapshot_SKU_Facility'
          AND object_id = OBJECT_ID(N'dbo.InventoryBalance')
    )
        EXEC sp_rename N'dbo.InventoryBalance.IX_JazzInventorySnapshot_SKU_Facility', N'IX_InventoryBalance_SKU_Facility', N'INDEX';
END;
GO
