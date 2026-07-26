/*
  NutraAxis Operations — Allow Adjustment sync type on QBOInventorySyncLog
  Used by portal shrink/gain posts (DocNumber NA-ADJ-{AdjustmentID}).
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
            N'Opening', N'Reconcile', N'Adjustment'
        )
    );
GO
