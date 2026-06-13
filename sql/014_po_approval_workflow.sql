/*
  NutraAxis Operations — PO approval workflow, log table, expanded statuses
*/

IF COL_LENGTH('dbo.Role', 'POApproval') IS NULL
    ALTER TABLE dbo.Role ADD POApproval NVARCHAR(10) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Role_POApproval_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_POApproval_CRUD
    CHECK (POApproval IS NULL OR POApproval IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

IF OBJECT_ID(N'dbo.CK_PurchaseOrder_POStatus', N'C') IS NOT NULL
    ALTER TABLE dbo.PurchaseOrder DROP CONSTRAINT CK_PurchaseOrder_POStatus;
GO

ALTER TABLE dbo.PurchaseOrder ALTER COLUMN POStatus NVARCHAR(50) NOT NULL;
GO

UPDATE dbo.PurchaseOrder SET POStatus = N'Created' WHERE POStatus = N'Draft';
UPDATE dbo.PurchaseOrder SET POStatus = N'Submitted for Approval' WHERE POStatus = N'Submitted';
UPDATE dbo.PurchaseOrder SET POStatus = N'Approved' WHERE POStatus = N'Approved';
UPDATE dbo.PurchaseOrder SET POStatus = N'Submitted to Accounting for Payment' WHERE POStatus = N'Received';
UPDATE dbo.PurchaseOrder SET POStatus = N'Created' WHERE POStatus = N'Cancelled';
GO

ALTER TABLE dbo.PurchaseOrder
ADD CONSTRAINT CK_PurchaseOrder_POStatus CHECK (
    POStatus IN (
        N'Created',
        N'Submitted for Approval',
        N'Rejected',
        N'Approved',
        N'Sent Back for Comment',
        N'Viewed by Approver',
        N'Submitted to Accounting for Payment',
        N'Paid'
    )
);
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
