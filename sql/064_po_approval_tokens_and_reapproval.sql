/*
  PO approval tokens, re-approval tracking, and designated PO approvers.
*/

IF COL_LENGTH('dbo.[User]', 'IsPOApprover') IS NULL
    ALTER TABLE dbo.[User] ADD IsPOApprover BIT NOT NULL
        CONSTRAINT DF_User_IsPOApprover DEFAULT (0);
GO

IF COL_LENGTH('dbo.PurchaseOrder', 'ApprovedTotalDue') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD ApprovedTotalDue DECIMAL(18, 2) NULL;
GO

IF COL_LENGTH('dbo.PurchaseOrder', 'ApprovedAt') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD ApprovedAt DATETIME2(0) NULL;
GO

IF COL_LENGTH('dbo.PurchaseOrder', 'RequiresReapproval') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD RequiresReapproval BIT NOT NULL
        CONSTRAINT DF_PurchaseOrder_RequiresReapproval DEFAULT (0);
GO

IF OBJECT_ID(N'dbo.POApprovalToken', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.POApprovalToken (
        TokenID      INT             NOT NULL IDENTITY(1,1),
        POID         INT             NOT NULL,
        UserID       INT             NOT NULL,
        TokenHash    CHAR(64)        NOT NULL,
        ExpiresAt    DATETIME2(0)    NOT NULL,
        UsedAt       DATETIME2(0)    NULL,
        CreatedAt    DATETIME2(0)    NOT NULL CONSTRAINT DF_POApprovalToken_CreatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_POApprovalToken PRIMARY KEY CLUSTERED (TokenID),
        CONSTRAINT FK_POApprovalToken_PurchaseOrder FOREIGN KEY (POID)
            REFERENCES dbo.PurchaseOrder (POID),
        CONSTRAINT FK_POApprovalToken_User FOREIGN KEY (UserID)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_POApprovalToken_POID
        ON dbo.POApprovalToken (POID, ExpiresAt DESC);

    CREATE NONCLUSTERED INDEX IX_POApprovalToken_Hash
        ON dbo.POApprovalToken (TokenHash)
        WHERE UsedAt IS NULL;
END;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.AlertMessage WHERE AlertName = N'po-approval-notice')
    INSERT INTO dbo.AlertMessage (AlertName, AlertStatus, AlertDescription)
    VALUES (N'po-approval-notice', 1, N'Notification that a PO was submitted for approval (no action links).');
GO

UPDATE u
SET IsPOApprover = 1
FROM dbo.[User] u
INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
WHERE r.POApproval LIKE N'%U%'
  AND ISNULL(u.IsPOApprover, 0) = 0;
GO

UPDATE po
SET ApprovedTotalDue = po.TotalDue,
    ApprovedAt = COALESCE(po.ModifiedDate, po.CreateDate)
FROM dbo.PurchaseOrder po
WHERE po.POStatus IN (N'Approved', N'Submitted to Accounting for Payment', N'Paid')
  AND po.ApprovedTotalDue IS NULL;
GO
