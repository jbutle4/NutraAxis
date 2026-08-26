/*
  NutraAxis Operations — Education Resources table + role permission
*/

IF OBJECT_ID(N'dbo.NA_Education_Resources', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.NA_Education_Resources (
        ERID            INT             NOT NULL IDENTITY(1, 1)
            CONSTRAINT PK_NA_Education_Resources PRIMARY KEY,
        Description     NVARCHAR(MAX)   NOT NULL,
        Type            NVARCHAR(25)    NOT NULL,
        URL             NVARCHAR(2000)  NULL,
        BlobPath        NVARCHAR(500)   NULL,
        FileName        NVARCHAR(255)   NULL,
        CreateDate      DATETIME2(0)    NOT NULL
            CONSTRAINT DF_NA_Education_Resources_CreateDate DEFAULT (SYSUTCDATETIME()),
        UpdateDate      DATETIME2(0)    NOT NULL
            CONSTRAINT DF_NA_Education_Resources_UpdateDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy       INT             NULL,
        CONSTRAINT CK_NA_Education_Resources_Type
            CHECK (Type IN (N'PDF', N'Video'))
    );

    CREATE INDEX IX_NA_Education_Resources_Type
        ON dbo.NA_Education_Resources (Type);

    CREATE INDEX IX_NA_Education_Resources_UpdateDate
        ON dbo.NA_Education_Resources (UpdateDate DESC);
END
GO

IF COL_LENGTH('dbo.Role', 'EducationResources') IS NULL
    ALTER TABLE dbo.Role ADD EducationResources NVARCHAR(10) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Role_EducationResources_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_EducationResources_CRUD
    CHECK (EducationResources IS NULL OR EducationResources IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

UPDATE dbo.Role
SET EducationResources = N'CRUD'
WHERE RoleName = N'Admin';
GO
