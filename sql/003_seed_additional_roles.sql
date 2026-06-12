/*
  NutraAxis Operations — seed additional IAM roles
*/

MERGE dbo.Role AS target
USING (
    VALUES
        (N'Management User', N'Management Operations User'),
        (N'Inventory User',   N'Inventory Operations User'),
        (N'Labeling User',    N'Labeling Operations User'),
        (N'Reporting User',   N'Reporting User')
) AS source (RoleName, RoleDesc)
    ON target.RoleName = source.RoleName
WHEN NOT MATCHED BY TARGET THEN
    INSERT (RoleName, RoleDesc, RoleCreateDate, ModifiedbyUser)
    VALUES (source.RoleName, source.RoleDesc, SYSUTCDATETIME(), NULL);
GO
