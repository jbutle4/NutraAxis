/*
  NutraAxis Operations — seed Admin role
*/

IF NOT EXISTS (
    SELECT 1 FROM dbo.Role WHERE RoleName = N'Admin'
)
BEGIN
    INSERT INTO dbo.Role (
        RoleName,
        RoleDesc,
        RoleCreateDate,
        ModifiedbyUser
    )
    VALUES (
        N'Admin',
        N'Site Administrator',
        SYSUTCDATETIME(),
        NULL
    );
END;
GO
