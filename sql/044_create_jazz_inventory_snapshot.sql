/*
  NutraAxis Operations — inventory balances by SKU and facility (Jazz and other sources)
*/

IF OBJECT_ID(N'dbo.InventoryBalance', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InventoryBalance (
        SnapshotID          INT             NOT NULL IDENTITY(1,1),
        SnapshotDateTime    DATETIME2(0)    NOT NULL,
        SKU                 NVARCHAR(100)   NOT NULL,
        FacilityCode        NVARCHAR(50)    NULL,
        AvailableQuantity   DECIMAL(18,4)   NOT NULL CONSTRAINT DF_InventoryBalance_Available DEFAULT (0),
        OnHandQuantity      DECIMAL(18,4)   NOT NULL CONSTRAINT DF_InventoryBalance_OnHand DEFAULT (0),
        QtyOrdered          DECIMAL(18,4)   NOT NULL CONSTRAINT DF_InventoryBalance_Ordered DEFAULT (0),
        TotalQuantity       DECIMAL(18,4)   NOT NULL CONSTRAINT DF_InventoryBalance_Total DEFAULT (0),

        CONSTRAINT PK_InventoryBalance PRIMARY KEY CLUSTERED (SnapshotID)
    );

    CREATE NONCLUSTERED INDEX IX_InventoryBalance_SnapshotDateTime
        ON dbo.InventoryBalance (SnapshotDateTime DESC);

    CREATE NONCLUSTERED INDEX IX_InventoryBalance_SKU_Facility
        ON dbo.InventoryBalance (SKU, FacilityCode, SnapshotDateTime DESC);
END;
GO
