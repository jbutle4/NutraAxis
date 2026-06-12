/*
  NutraAxis Operations — weekly Jazz OMS inventory snapshots
*/

IF OBJECT_ID(N'dbo.JazzInventorySnapshot', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.JazzInventorySnapshot (
        SnapshotID          INT             NOT NULL IDENTITY(1,1),
        SnapshotDateTime    DATETIME2(0)    NOT NULL,
        SKU                 NVARCHAR(100)   NOT NULL,
        FacilityCode        NVARCHAR(50)    NULL,
        AvailableQuantity   DECIMAL(18,4)   NOT NULL CONSTRAINT DF_JazzInventorySnapshot_Available DEFAULT (0),
        OnHandQuantity      DECIMAL(18,4)   NOT NULL CONSTRAINT DF_JazzInventorySnapshot_OnHand DEFAULT (0),
        QtyOrdered          DECIMAL(18,4)   NOT NULL CONSTRAINT DF_JazzInventorySnapshot_Ordered DEFAULT (0),
        TotalQuantity       DECIMAL(18,4)   NOT NULL CONSTRAINT DF_JazzInventorySnapshot_Total DEFAULT (0),

        CONSTRAINT PK_JazzInventorySnapshot PRIMARY KEY CLUSTERED (SnapshotID)
    );

    CREATE NONCLUSTERED INDEX IX_JazzInventorySnapshot_SnapshotDateTime
        ON dbo.JazzInventorySnapshot (SnapshotDateTime DESC);

    CREATE NONCLUSTERED INDEX IX_JazzInventorySnapshot_SKU_Facility
        ON dbo.JazzInventorySnapshot (SKU, FacilityCode, SnapshotDateTime DESC);
END;
GO
