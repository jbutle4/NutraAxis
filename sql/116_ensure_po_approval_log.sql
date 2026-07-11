/*
  NutraAxis Operations — ensure POApprovalLog exists on Azure and backfill legacy rows
  from unified dbo.ApprovalLog when present.
*/

DECLARE @dropSql NVARCHAR(MAX);
SELECT @dropSql =
    N'ALTER TABLE ' + QUOTENAME(OBJECT_SCHEMA_NAME(parent_object_id)) + N'.' + QUOTENAME(OBJECT_NAME(parent_object_id))
    + N' DROP CONSTRAINT DF_POApprovalLog_LogDate'
FROM sys.objects
WHERE name = N'DF_POApprovalLog_LogDate' AND type = 'D';
IF @dropSql IS NOT NULL
    EXEC sp_executesql @dropSql;
GO

IF OBJECT_ID(N'dbo.POApprovalLog', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.POApprovalLog (
        ApprovalID        INT             NOT NULL IDENTITY(1,1),
        POID              INT             NOT NULL,
        ApproverName      NVARCHAR(200)   NOT NULL,
        ApproverResult    NVARCHAR(100)   NOT NULL,
        ApproverComments  NVARCHAR(MAX)   NULL,
        LogDate           DATETIME2(0)    NOT NULL CONSTRAINT DF_POApprovalLog_LogDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT PK_POApprovalLog PRIMARY KEY CLUSTERED (ApprovalID),
        CONSTRAINT FK_POApprovalLog_PurchaseOrder FOREIGN KEY (POID)
            REFERENCES dbo.PurchaseOrder (POID) ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX IX_POApprovalLog_POID
        ON dbo.POApprovalLog (POID, LogDate DESC);
END;
GO

IF OBJECT_ID(N'dbo.ApprovalLog', N'U') IS NOT NULL
AND COL_LENGTH('dbo.ApprovalLog', 'ApprovalType') IS NOT NULL
BEGIN
    INSERT INTO dbo.POApprovalLog (POID, ApproverName, ApproverResult, ApproverComments, LogDate)
    SELECT
        al.EntityID,
        al.ApproverName,
        al.ApproverResult,
        al.ApproverComments,
        al.LogDate
    FROM dbo.ApprovalLog al
    WHERE al.ApprovalType = N'PO'
      AND al.EntityType = N'PurchaseOrder'
      AND al.EntityID IS NOT NULL
      AND NOT EXISTS (
          SELECT 1
          FROM dbo.POApprovalLog pl
          WHERE pl.POID = al.EntityID
            AND pl.LogDate = al.LogDate
            AND pl.ApproverName = al.ApproverName
      );
END;
GO
