/*
  NutraAxis Operations — PO Approver role
  POManagement = R (view PO list and details)
  POApproval = RU (view approval queue and take approval actions)
*/

MERGE dbo.Role AS target
USING (
    SELECT
        N'PO Approver' AS RoleName,
        N'Review and approve purchase orders submitted for approval.' AS RoleDesc,
        N'R' AS POManagement,
        N'RU' AS POApproval
) AS source
    ON target.RoleName = source.RoleName
WHEN MATCHED THEN
    UPDATE SET
        RoleDesc = source.RoleDesc,
        POManagement = source.POManagement,
        POApproval = source.POApproval,
        ModifiedbyUser = 1
WHEN NOT MATCHED BY TARGET THEN
    INSERT (RoleName, RoleDesc, RoleCreateDate, ModifiedbyUser, POManagement, POApproval)
    VALUES (source.RoleName, source.RoleDesc, SYSUTCDATETIME(), 1, source.POManagement, source.POApproval);
GO
