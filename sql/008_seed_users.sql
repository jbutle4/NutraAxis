/*
  NutraAxis Operations — seed initial users
  Role 2 = Management User (verify RoleID before running in other environments)

  Field mapping per row:
    UserName, UserLogin, UserPassword, UserAssignedRole,
    CreateDate, ModifiedDate, LastPasswordReset, Modifiedbyuser
  LastLoginDate left NULL until first login.
*/

DECLARE @now datetime2(0) = SYSUTCDATETIME();
DECLARE @roleId int = 2;
DECLARE @modifiedBy int = 1;

-- First user may not exist yet; allow NULL Modifiedbyuser until UserID 1 is present.
IF NOT EXISTS (SELECT 1 FROM dbo.[User] WHERE UserID = @modifiedBy)
    SET @modifiedBy = NULL;

IF NOT EXISTS (SELECT 1 FROM dbo.[User] WHERE UserLogin = N'jstoneking@wellsrx.com')
BEGIN
    INSERT INTO dbo.[User] (
        UserName,
        UserLogin,
        UserPassword,
        UserAssignedRole,
        CreateDate,
        ModifiedDate,
        LastPasswordReset,
        LastLoginDate,
        Modifiedbyuser
    )
    VALUES (
        N'Josh Stoneking',
        N'jstoneking@wellsrx.com',
        N'welcome1',
        @roleId,
        @now,
        @now,
        @now,
        NULL,
        @modifiedBy
    );
END;
GO

DECLARE @now datetime2(0) = SYSUTCDATETIME();
DECLARE @roleId int = 2;
DECLARE @modifiedBy int = 1;

IF NOT EXISTS (SELECT 1 FROM dbo.[User] WHERE UserID = @modifiedBy)
    SET @modifiedBy = NULL;

IF NOT EXISTS (SELECT 1 FROM dbo.[User] WHERE UserLogin = N'mlandis@nfcllc.com')
BEGIN
    INSERT INTO dbo.[User] (
        UserName,
        UserLogin,
        UserPassword,
        UserAssignedRole,
        CreateDate,
        ModifiedDate,
        LastPasswordReset,
        LastLoginDate,
        Modifiedbyuser
    )
    VALUES (
        N'Madison Landis',
        N'mlandis@nfcllc.com',
        N'welcome1',
        @roleId,
        @now,
        @now,
        @now,
        NULL,
        @modifiedBy
    );
END;
GO

DECLARE @now datetime2(0) = SYSUTCDATETIME();
DECLARE @roleId int = 2;
DECLARE @modifiedBy int = 1;

IF NOT EXISTS (SELECT 1 FROM dbo.[User] WHERE UserID = @modifiedBy)
    SET @modifiedBy = NULL;

IF NOT EXISTS (SELECT 1 FROM dbo.[User] WHERE UserLogin = N'jrichmond@nfcllc.com')
BEGIN
    INSERT INTO dbo.[User] (
        UserName,
        UserLogin,
        UserPassword,
        UserAssignedRole,
        CreateDate,
        ModifiedDate,
        LastPasswordReset,
        LastLoginDate,
        Modifiedbyuser
    )
    VALUES (
        N'Jennifer Richmond',
        N'jrichmond@nfcllc.com',
        N'welcome1',
        @roleId,
        @now,
        @now,
        @now,
        NULL,
        @modifiedBy
    );
END;
GO

-- Set Modifiedbyuser = 1 once UserID 1 exists (Josh if first insert).
IF EXISTS (SELECT 1 FROM dbo.[User] WHERE UserID = 1)
BEGIN
    UPDATE dbo.[User]
    SET Modifiedbyuser = 1
    WHERE UserLogin IN (
        N'jstoneking@wellsrx.com',
        N'mlandis@nfcllc.com',
        N'jrichmond@nfcllc.com'
    )
      AND (Modifiedbyuser IS NULL OR Modifiedbyuser <> 1);
END;
GO
