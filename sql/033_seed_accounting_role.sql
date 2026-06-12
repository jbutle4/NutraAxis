/*
  NutraAxis Operations — Accounting role (read-only QuickBooks views)
*/

MERGE dbo.Role AS target
USING (
    VALUES (
        N'Accounting',
        N'View QuickBooks Online AP, AR, purchase orders, inventory, suppliers, and chart of accounts in Operations.',
        N'R'
    )
) AS source (RoleName, RoleDesc, AccountingPermission)
    ON target.RoleName = source.RoleName
WHEN MATCHED THEN
    UPDATE SET
        RoleDesc = source.RoleDesc,
        Accounting = source.AccountingPermission,
        OperationsDashboard = COALESCE(target.OperationsDashboard, N'R'),
        ModifiedbyUser = 1
WHEN NOT MATCHED BY TARGET THEN
    INSERT (
        RoleName,
        RoleDesc,
        RoleCreateDate,
        ModifiedbyUser,
        Accounting,
        OperationsDashboard
    )
    VALUES (
        source.RoleName,
        source.RoleDesc,
        SYSUTCDATETIME(),
        1,
        source.AccountingPermission,
        N'R'
    );
GO
