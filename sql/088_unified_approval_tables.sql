/*
  NutraAxis Operations — Unified ApprovalLog / ApprovalToken for all approval types

  Evolves POApprovalLog + POApprovalToken into generic tables, migrates T&E and
  invoice payment approval history, then drops legacy tables.
*/

IF OBJECT_ID(N'dbo.ApprovalLog', N'U') IS NULL AND OBJECT_ID(N'dbo.POApprovalLog', N'U') IS NOT NULL
    EXEC sp_rename N'dbo.POApprovalLog', N'ApprovalLog';
GO

IF OBJECT_ID(N'dbo.ApprovalToken', N'U') IS NULL AND OBJECT_ID(N'dbo.POApprovalToken', N'U') IS NOT NULL
    EXEC sp_rename N'dbo.POApprovalToken', N'ApprovalToken';
GO

IF COL_LENGTH('dbo.ApprovalLog', 'ApprovalType') IS NULL
    ALTER TABLE dbo.ApprovalLog ADD ApprovalType NVARCHAR(30) NULL;
GO

IF COL_LENGTH('dbo.ApprovalLog', 'EntityType') IS NULL
    ALTER TABLE dbo.ApprovalLog ADD EntityType NVARCHAR(50) NULL;
GO

IF COL_LENGTH('dbo.ApprovalLog', 'EntityID') IS NULL
    ALTER TABLE dbo.ApprovalLog ADD EntityID INT NULL;
GO

IF COL_LENGTH('dbo.ApprovalLog', 'SecondaryEntityType') IS NULL
    ALTER TABLE dbo.ApprovalLog ADD SecondaryEntityType NVARCHAR(50) NULL;
GO

IF COL_LENGTH('dbo.ApprovalLog', 'SecondaryEntityID') IS NULL
    ALTER TABLE dbo.ApprovalLog ADD SecondaryEntityID INT NULL;
GO

IF COL_LENGTH('dbo.ApprovalLog', 'ApproverUserID') IS NULL
    ALTER TABLE dbo.ApprovalLog ADD ApproverUserID INT NULL;
GO

IF COL_LENGTH('dbo.ApprovalLog', 'ApprovalType') IS NOT NULL
   AND EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(N'dbo.ApprovalLog') AND name = N'POID')
BEGIN
    UPDATE dbo.ApprovalLog
    SET ApprovalType = N'PO',
        EntityType = N'PurchaseOrder',
        EntityID = POID
    WHERE ApprovalType IS NULL;
END;
GO

IF OBJECT_ID(N'dbo.TEApprovalLog', N'U') IS NOT NULL
BEGIN
    INSERT INTO dbo.ApprovalLog (
        ApprovalType, EntityType, EntityID,
        ApproverName, ApproverResult, ApproverComments, LogDate
    )
    SELECT
        N'TE',
        N'TEReport',
        ReportID,
        ApproverName,
        ApproverResult,
        ApproverComments,
        LogDate
    FROM dbo.TEApprovalLog;
END;
GO

IF OBJECT_ID(N'dbo.InvoicePaymentApprovalLog', N'U') IS NOT NULL
BEGIN
    INSERT INTO dbo.ApprovalLog (
        ApprovalType, EntityType, EntityID,
        SecondaryEntityType, SecondaryEntityID,
        ApproverName, ApproverResult, ApproverComments, LogDate
    )
    SELECT
        N'Payment',
        N'POPayment',
        PaymentID,
        N'SupplierInvoice',
        SupplierInvoiceID,
        ApproverName,
        ApproverResult,
        ApproverComments,
        LogDate
    FROM dbo.InvoicePaymentApprovalLog;
END;
GO

IF COL_LENGTH('dbo.ApprovalLog', 'ApprovalType') IS NOT NULL
BEGIN
    ALTER TABLE dbo.ApprovalLog ALTER COLUMN ApprovalType NVARCHAR(30) NOT NULL;
    ALTER TABLE dbo.ApprovalLog ALTER COLUMN EntityType NVARCHAR(50) NOT NULL;
    ALTER TABLE dbo.ApprovalLog ALTER COLUMN EntityID INT NOT NULL;
END;
GO

IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(N'dbo.ApprovalLog') AND name = N'POID')
BEGIN
    IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_POApprovalLog_PurchaseOrder')
        ALTER TABLE dbo.ApprovalLog DROP CONSTRAINT FK_POApprovalLog_PurchaseOrder;

    IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_POApprovalLog_POID' AND object_id = OBJECT_ID(N'dbo.ApprovalLog'))
        DROP INDEX IX_POApprovalLog_POID ON dbo.ApprovalLog;

    ALTER TABLE dbo.ApprovalLog DROP COLUMN POID;
END;
GO

IF OBJECT_ID(N'dbo.CK_ApprovalLog_ApprovalType', N'C') IS NULL
    ALTER TABLE dbo.ApprovalLog
        ADD CONSTRAINT CK_ApprovalLog_ApprovalType CHECK (
            ApprovalType IN (N'PO', N'TE', N'QBOInsert', N'Payment')
        );
GO

IF OBJECT_ID(N'dbo.FK_ApprovalLog_ApproverUser', N'F') IS NULL
    ALTER TABLE dbo.ApprovalLog
        ADD CONSTRAINT FK_ApprovalLog_ApproverUser FOREIGN KEY (ApproverUserID)
            REFERENCES dbo.[User] (UserID);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ApprovalLog_Entity' AND object_id = OBJECT_ID(N'dbo.ApprovalLog'))
    CREATE NONCLUSTERED INDEX IX_ApprovalLog_Entity
        ON dbo.ApprovalLog (ApprovalType, EntityType, EntityID, LogDate DESC);
GO

IF COL_LENGTH('dbo.ApprovalToken', 'ApprovalType') IS NULL
    ALTER TABLE dbo.ApprovalToken ADD ApprovalType NVARCHAR(30) NULL;
GO

IF COL_LENGTH('dbo.ApprovalToken', 'EntityType') IS NULL
    ALTER TABLE dbo.ApprovalToken ADD EntityType NVARCHAR(50) NULL;
GO

IF COL_LENGTH('dbo.ApprovalToken', 'EntityID') IS NULL
    ALTER TABLE dbo.ApprovalToken ADD EntityID INT NULL;
GO

IF COL_LENGTH('dbo.ApprovalToken', 'SecondaryEntityType') IS NULL
    ALTER TABLE dbo.ApprovalToken ADD SecondaryEntityType NVARCHAR(50) NULL;
GO

IF COL_LENGTH('dbo.ApprovalToken', 'SecondaryEntityID') IS NULL
    ALTER TABLE dbo.ApprovalToken ADD SecondaryEntityID INT NULL;
GO

IF COL_LENGTH('dbo.ApprovalToken', 'ApprovalType') IS NOT NULL
   AND EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(N'dbo.ApprovalToken') AND name = N'POID')
BEGIN
    UPDATE dbo.ApprovalToken
    SET ApprovalType = N'PO',
        EntityType = N'PurchaseOrder',
        EntityID = POID
    WHERE ApprovalType IS NULL;
END;
GO

IF OBJECT_ID(N'dbo.TEApprovalToken', N'U') IS NOT NULL
BEGIN
    INSERT INTO dbo.ApprovalToken (
        ApprovalType, EntityType, EntityID,
        UserID, TokenHash, ExpiresAt, UsedAt, CreatedAt
    )
    SELECT
        N'TE',
        N'TEReport',
        ReportID,
        UserID,
        TokenHash,
        ExpiresAt,
        UsedAt,
        CreatedAt
    FROM dbo.TEApprovalToken;
END;
GO

IF OBJECT_ID(N'dbo.InvoicePaymentApprovalToken', N'U') IS NOT NULL
BEGIN
    INSERT INTO dbo.ApprovalToken (
        ApprovalType, EntityType, EntityID,
        SecondaryEntityType, SecondaryEntityID,
        UserID, TokenHash, ExpiresAt, UsedAt, CreatedAt
    )
    SELECT
        N'Payment',
        N'POPayment',
        PaymentID,
        N'SupplierInvoice',
        SupplierInvoiceID,
        UserID,
        TokenHash,
        ExpiresAt,
        UsedAt,
        CreatedAt
    FROM dbo.InvoicePaymentApprovalToken;
END;
GO

IF COL_LENGTH('dbo.ApprovalToken', 'ApprovalType') IS NOT NULL
BEGIN
    ALTER TABLE dbo.ApprovalToken ALTER COLUMN ApprovalType NVARCHAR(30) NOT NULL;
    ALTER TABLE dbo.ApprovalToken ALTER COLUMN EntityType NVARCHAR(50) NOT NULL;
    ALTER TABLE dbo.ApprovalToken ALTER COLUMN EntityID INT NOT NULL;
END;
GO

IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(N'dbo.ApprovalToken') AND name = N'POID')
BEGIN
    IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_POApprovalToken_PurchaseOrder')
        ALTER TABLE dbo.ApprovalToken DROP CONSTRAINT FK_POApprovalToken_PurchaseOrder;

    IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_POApprovalToken_POID' AND object_id = OBJECT_ID(N'dbo.ApprovalToken'))
        DROP INDEX IX_POApprovalToken_POID ON dbo.ApprovalToken;

    ALTER TABLE dbo.ApprovalToken DROP COLUMN POID;
END;
GO

IF OBJECT_ID(N'dbo.CK_ApprovalToken_ApprovalType', N'C') IS NULL
    ALTER TABLE dbo.ApprovalToken
        ADD CONSTRAINT CK_ApprovalToken_ApprovalType CHECK (
            ApprovalType IN (N'PO', N'TE', N'QBOInsert', N'Payment')
        );
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ApprovalToken_Entity' AND object_id = OBJECT_ID(N'dbo.ApprovalToken'))
    CREATE NONCLUSTERED INDEX IX_ApprovalToken_Entity
        ON dbo.ApprovalToken (ApprovalType, EntityType, EntityID, ExpiresAt DESC);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ApprovalToken_Hash' AND object_id = OBJECT_ID(N'dbo.ApprovalToken'))
    CREATE NONCLUSTERED INDEX IX_ApprovalToken_Hash
        ON dbo.ApprovalToken (TokenHash)
        WHERE UsedAt IS NULL;
GO

IF OBJECT_ID(N'dbo.TEApprovalLog', N'U') IS NOT NULL
    DROP TABLE dbo.TEApprovalLog;
GO

IF OBJECT_ID(N'dbo.TEApprovalToken', N'U') IS NOT NULL
    DROP TABLE dbo.TEApprovalToken;
GO

IF OBJECT_ID(N'dbo.InvoicePaymentApprovalLog', N'U') IS NOT NULL
    DROP TABLE dbo.InvoicePaymentApprovalLog;
GO

IF OBJECT_ID(N'dbo.InvoicePaymentApprovalToken', N'U') IS NOT NULL
    DROP TABLE dbo.InvoicePaymentApprovalToken;
GO

IF OBJECT_ID(N'dbo.PK_POApprovalLog', N'PK') IS NOT NULL
    EXEC sp_rename N'dbo.PK_POApprovalLog', N'PK_ApprovalLog', N'OBJECT';
GO

IF OBJECT_ID(N'dbo.PK_POApprovalToken', N'PK') IS NOT NULL
    EXEC sp_rename N'dbo.PK_POApprovalToken', N'PK_ApprovalToken', N'OBJECT';
GO
