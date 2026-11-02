/*
  NutraAxis Operations — Sales Receipt cutover sync types on QBOInventorySyncLog
  SaleReceipt / SaleReversal + Superseded status for mistaken sale adjustments.
*/

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = N'CK_QBOInvSync_Type'
      AND parent_object_id = OBJECT_ID(N'dbo.QBOInventorySyncLog')
)
BEGIN
    ALTER TABLE dbo.QBOInventorySyncLog DROP CONSTRAINT CK_QBOInvSync_Type;
END;
GO

ALTER TABLE dbo.QBOInventorySyncLog
    ADD CONSTRAINT CK_QBOInvSync_Type CHECK (
        SyncType IN (
            N'Receipt', N'Sale', N'TransferJE', N'TransferAdj',
            N'Opening', N'Reconcile', N'Adjustment',
            N'SaleReceipt', N'SaleReversal'
        )
    );
GO

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = N'CK_QBOInvSync_Status'
      AND parent_object_id = OBJECT_ID(N'dbo.QBOInventorySyncLog')
)
BEGIN
    ALTER TABLE dbo.QBOInventorySyncLog DROP CONSTRAINT CK_QBOInvSync_Status;
END;
GO

ALTER TABLE dbo.QBOInventorySyncLog
    ADD CONSTRAINT CK_QBOInvSync_Status CHECK (
        SyncStatus IN (N'Pending', N'Synced', N'Error', N'Skipped', N'Superseded')
    );
GO
