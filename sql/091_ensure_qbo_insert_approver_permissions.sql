/*
  Ensure QBO insert and payment approver permissions exist on operational roles.

  Safe to re-run. Complements sql/087_approval_role_permissions.sql when that
  migration was not applied or roles were edited afterward in Site Admin.
*/

IF COL_LENGTH('dbo.Role', 'QBOInsertApproval') IS NULL
    ALTER TABLE dbo.Role ADD QBOInsertApproval NVARCHAR(10) NULL;
GO

IF COL_LENGTH('dbo.Role', 'PaymentApproval') IS NULL
    ALTER TABLE dbo.Role ADD PaymentApproval NVARCHAR(10) NULL;
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
        Accounting = COALESCE(target.Accounting, source.Accounting),
        QBOInsertApproval = COALESCE(target.QBOInsertApproval, source.QBOInsertApproval),
        OperationsDashboard = COALESCE(target.OperationsDashboard, source.OperationsDashboard),
        ModifiedbyUser = 1
WHEN NOT MATCHED BY TARGET THEN
    INSERT (RoleName, RoleDesc, RoleCreateDate, ModifiedbyUser, Accounting, QBOInsertApproval, OperationsDashboard)
    VALUES (source.RoleName, source.RoleDesc, SYSUTCDATETIME(), 1, source.Accounting, source.QBOInsertApproval, source.OperationsDashboard);
GO

UPDATE dbo.Role
SET
    QBOInsertApproval = N'CRUD',
    PaymentApproval = N'CRUD',
    ModifiedbyUser = 1
WHERE RoleName = N'Admin'
  AND (QBOInsertApproval IS NULL OR QBOInsertApproval = N'');
GO

UPDATE dbo.Role
SET
    QBOInsertApproval = N'RU',
    PaymentApproval = N'RU',
    ModifiedbyUser = 1
WHERE RoleName = N'Management User'
  AND (QBOInsertApproval IS NULL OR QBOInsertApproval = N'');
GO

UPDATE dbo.Role
SET
    QBOInsertApproval = N'CRUD',
    PaymentApproval = N'CRUD',
    ModifiedbyUser = 1
WHERE RoleName = N'All Approver'
  AND (
      QBOInsertApproval IS NULL
      OR QBOInsertApproval = N''
      OR QBOInsertApproval NOT LIKE N'%U%'
      OR PaymentApproval IS NULL
      OR PaymentApproval = N''
      OR PaymentApproval NOT LIKE N'%U%'
  );
GO
