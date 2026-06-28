/*
  NutraAxis Operations — Approval permissions on Role + approver roles

  Moves PO/T&E/payment/QBO insert approver and T&E processor designation
  from User flags to Role permission columns.
*/

IF COL_LENGTH('dbo.Role', 'QBOInsertApproval') IS NULL
    ALTER TABLE dbo.Role ADD QBOInsertApproval NVARCHAR(10) NULL;
GO

IF COL_LENGTH('dbo.Role', 'PaymentApproval') IS NULL
    ALTER TABLE dbo.Role ADD PaymentApproval NVARCHAR(10) NULL;
GO

IF COL_LENGTH('dbo.Role', 'TEProcessing') IS NULL
    ALTER TABLE dbo.Role ADD TEProcessing NVARCHAR(10) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Role_QBOInsertApproval_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_QBOInsertApproval_CRUD
    CHECK (QBOInsertApproval IS NULL OR QBOInsertApproval IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

IF OBJECT_ID(N'dbo.CK_Role_PaymentApproval_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_PaymentApproval_CRUD
    CHECK (PaymentApproval IS NULL OR PaymentApproval IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

IF OBJECT_ID(N'dbo.CK_Role_TEProcessing_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_TEProcessing_CRUD
    CHECK (TEProcessing IS NULL OR TEProcessing IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

MERGE dbo.Role AS target
USING (
    SELECT
        N'PO Approver' AS RoleName,
        N'Review and approve purchase orders submitted for approval.' AS RoleDesc,
        N'R' AS POManagement,
        N'RU' AS POApproval,
        N'R' AS OperationsDashboard,
        N'R' AS ProductCatalog
) AS source
    ON target.RoleName = source.RoleName
WHEN MATCHED THEN
    UPDATE SET
        RoleDesc = source.RoleDesc,
        POManagement = source.POManagement,
        POApproval = source.POApproval,
        OperationsDashboard = source.OperationsDashboard,
        ProductCatalog = source.ProductCatalog,
        ModifiedbyUser = 1
WHEN NOT MATCHED BY TARGET THEN
    INSERT (RoleName, RoleDesc, RoleCreateDate, ModifiedbyUser, POManagement, POApproval, OperationsDashboard, ProductCatalog)
    VALUES (source.RoleName, source.RoleDesc, SYSUTCDATETIME(), 1, source.POManagement, source.POApproval, source.OperationsDashboard, source.ProductCatalog);
GO

MERGE dbo.Role AS target
USING (
    SELECT
        N'QBO Insert Approver' AS RoleName,
        N'Review and approve supplier invoices before posting bills to QuickBooks Online.' AS RoleDesc,
        N'R' AS Accounting,
        N'RU' AS QBOInsertApproval,
        N'R' AS OperationsDashboard
) AS source
    ON target.RoleName = source.RoleName
WHEN MATCHED THEN
    UPDATE SET
        RoleDesc = source.RoleDesc,
        Accounting = source.Accounting,
        QBOInsertApproval = source.QBOInsertApproval,
        OperationsDashboard = source.OperationsDashboard,
        ModifiedbyUser = 1
WHEN NOT MATCHED BY TARGET THEN
    INSERT (RoleName, RoleDesc, RoleCreateDate, ModifiedbyUser, Accounting, QBOInsertApproval, OperationsDashboard)
    VALUES (source.RoleName, source.RoleDesc, SYSUTCDATETIME(), 1, source.Accounting, source.QBOInsertApproval, source.OperationsDashboard);
GO

MERGE dbo.Role AS target
USING (
    SELECT
        N'Payment Approver' AS RoleName,
        N'Review and approve supplier invoice payments before they are recorded or transmitted.' AS RoleDesc,
        N'R' AS Accounting,
        N'RU' AS PaymentApproval,
        N'R' AS OperationsDashboard
) AS source
    ON target.RoleName = source.RoleName
WHEN MATCHED THEN
    UPDATE SET
        RoleDesc = source.RoleDesc,
        Accounting = source.Accounting,
        PaymentApproval = source.PaymentApproval,
        OperationsDashboard = source.OperationsDashboard,
        ModifiedbyUser = 1
WHEN NOT MATCHED BY TARGET THEN
    INSERT (RoleName, RoleDesc, RoleCreateDate, ModifiedbyUser, Accounting, PaymentApproval, OperationsDashboard)
    VALUES (source.RoleName, source.RoleDesc, SYSUTCDATETIME(), 1, source.Accounting, source.PaymentApproval, source.OperationsDashboard);
GO

UPDATE dbo.Role
SET
    TEManagement = N'R',
    TEProcessing = N'R',
    OperationsDashboard = N'R',
    ProductCatalog = N'R',
    ModifiedbyUser = 1
WHERE RoleName = N'PO Processor';
GO

UPDATE dbo.Role
SET
    TEManagement = N'R',
    TEApproval = N'RU',
    OperationsDashboard = N'R',
    ProductCatalog = N'R',
    ModifiedbyUser = 1
WHERE RoleName = N'T&E Approver';
GO

UPDATE dbo.Role
SET
    POApproval = N'CRUD',
    TEApproval = N'CRUD',
    QBOInsertApproval = N'CRUD',
    PaymentApproval = N'CRUD',
    TEProcessing = N'CRUD',
    ModifiedbyUser = 1
WHERE RoleName = N'Admin';
GO

UPDATE dbo.Role
SET
    POApproval = N'RU',
    TEApproval = N'RU',
    QBOInsertApproval = N'RU',
    PaymentApproval = N'RU',
    TEProcessing = N'R',
    ModifiedbyUser = 1
WHERE RoleName = N'Management User';
GO

UPDATE dbo.[User]
SET
    IsPOApprover = 0,
    IsTEApprover = 0,
    IsPOProcessor = 0;
GO
