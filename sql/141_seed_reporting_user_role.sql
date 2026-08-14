/*
  NutraAxis Operations — Reporting User role

  Read-only Sales Reporting hub access only (SalesReporting = R).
  No other module permissions. Portal shows only the Sales Reporting hub
  for this role (see auth_is_reporting_user / auth_filter_modules).

  Run:
    node scripts/run-sql-file.js sql/141_seed_reporting_user_role.sql
*/

IF NOT EXISTS (SELECT 1 FROM dbo.Role WHERE RoleName = N'Reporting User')
BEGIN
    INSERT INTO dbo.Role (RoleName, RoleDesc, RoleCreateDate, ModifiedbyUser, SalesReporting)
    VALUES (
        N'Reporting User',
        N'Read-only access to Sales Reporting (orders, summaries, sales-rep reporting, and territory assignments).',
        SYSUTCDATETIME(),
        1,
        N'R'
    );
END
GO

UPDATE dbo.Role
SET
    RoleDesc = N'Read-only access to Sales Reporting (orders, summaries, sales-rep reporting, and territory assignments).',
    POManagement = NULL,
    InventoryReporting = NULL,
    SalesReporting = N'R',
    InventoryForecasting = NULL,
    LabelingOperations = NULL,
    OperationsDashboard = NULL,
    LegalAgreements = NULL,
    ProductCatalog = NULL,
    LinksIndex = NULL,
    ContactsList = NULL,
    Support = NULL,
    Accounting = NULL,
    UserAdmin = NULL,
    RoleAdmin = NULL,
    POApproval = NULL,
    ModifiedbyUser = 1
WHERE RoleName = N'Reporting User';
GO

IF COL_LENGTH('dbo.Role', 'TEManagement') IS NOT NULL
    UPDATE dbo.Role SET TEManagement = NULL WHERE RoleName = N'Reporting User';
IF COL_LENGTH('dbo.Role', 'TEApproval') IS NOT NULL
    UPDATE dbo.Role SET TEApproval = NULL WHERE RoleName = N'Reporting User';
IF COL_LENGTH('dbo.Role', 'TEProcessing') IS NOT NULL
    UPDATE dbo.Role SET TEProcessing = NULL WHERE RoleName = N'Reporting User';
IF COL_LENGTH('dbo.Role', 'QBOInsertApproval') IS NOT NULL
    UPDATE dbo.Role SET QBOInsertApproval = NULL WHERE RoleName = N'Reporting User';
IF COL_LENGTH('dbo.Role', 'PaymentApproval') IS NOT NULL
    UPDATE dbo.Role SET PaymentApproval = NULL WHERE RoleName = N'Reporting User';
IF COL_LENGTH('dbo.Role', 'ProviderAccountReview') IS NOT NULL
    UPDATE dbo.Role SET ProviderAccountReview = NULL WHERE RoleName = N'Reporting User';
GO
