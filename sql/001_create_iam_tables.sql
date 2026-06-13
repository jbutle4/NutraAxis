/*
  NutraAxis Operations — IAM schema
  Database: nutraaxis
  Creates Role and [User] tables with FK relationship.

  Permission columns (POManagement, UserAdmin, etc.) store CRUD subsets:
  C=Create, R=Read, U=Update, D=Delete — canonical order, NULL=no access.
  Run 006_permission_crud_constraints.sql after create to enforce valid values.
*/

-- Role must exist before User (FK dependency)
IF OBJECT_ID(N'dbo.Role', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.Role (
        RoleID          INT             NOT NULL IDENTITY(1,1),
        RoleName        NVARCHAR(100)   NOT NULL,
        RoleDesc        NVARCHAR(MAX)   NULL,
        RoleCreateDate         DATETIME2(0)    NOT NULL CONSTRAINT DF_Role_RoleCreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedbyUser         INT             NULL,
        POManagement           NVARCHAR(10)    NULL,
        InventoryReporting     NVARCHAR(10)    NULL,
        SalesReporting         NVARCHAR(10)    NULL,
        InventoryForecasting   NVARCHAR(10)    NULL,
        LabelingOperations     NVARCHAR(10)    NULL,
        OperationsDashboard    NVARCHAR(10)    NULL,
        UserAdmin              NVARCHAR(10)    NULL,
        RoleAdmin              NVARCHAR(10)    NULL,

        CONSTRAINT PK_Role PRIMARY KEY CLUSTERED (RoleID),
        CONSTRAINT UQ_Role_RoleName UNIQUE (RoleName)
    );
END;
GO

IF OBJECT_ID(N'dbo.[User]', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[User] (
        UserID              INT             NOT NULL IDENTITY(1,1),
        UserName            NVARCHAR(200)   NOT NULL,
        UserLogin           NVARCHAR(100)   NOT NULL,
        UserPassword        NVARCHAR(256)   NOT NULL,
        UserAssignedRole    INT             NOT NULL,
        CreateDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_User_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate        DATETIME2(0)    NOT NULL CONSTRAINT DF_User_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        LastPasswordReset   DATETIME2(0)    NULL,
        LastLoginDate       DATETIME2(0)    NULL,
        Modifiedbyuser      INT             NULL,

        CONSTRAINT PK_User PRIMARY KEY CLUSTERED (UserID),
        CONSTRAINT UQ_User_UserLogin UNIQUE (UserLogin),
        CONSTRAINT FK_User_Role FOREIGN KEY (UserAssignedRole)
            REFERENCES dbo.Role (RoleID),
        CONSTRAINT FK_User_ModifiedByUser FOREIGN KEY (Modifiedbyuser)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_User_UserAssignedRole
        ON dbo.[User] (UserAssignedRole);

    CREATE NONCLUSTERED INDEX IX_User_LastLoginDate
        ON dbo.[User] (LastLoginDate);
END;
GO

-- Optional: self-FK on Role.ModifiedbyUser after User table exists
IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE name = N'FK_Role_ModifiedByUser'
      AND parent_object_id = OBJECT_ID(N'dbo.Role')
)
BEGIN
    ALTER TABLE dbo.Role
        ADD CONSTRAINT FK_Role_ModifiedByUser
            FOREIGN KEY (ModifiedbyUser) REFERENCES dbo.[User] (UserID);
END;
GO
