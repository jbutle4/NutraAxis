/*
  Provider signup — ACCS clinic configuration step tracking on application row.
*/

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsStepClinicDone') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsStepClinicDone BIT NOT NULL
        CONSTRAINT DF_ProviderSignupApplication_AccsStepClinicDone DEFAULT (0);
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsStepClinicAt') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsStepClinicAt DATETIME2(0) NULL;
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsStepAdminDone') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsStepAdminDone BIT NOT NULL
        CONSTRAINT DF_ProviderSignupApplication_AccsStepAdminDone DEFAULT (0);
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsStepAdminAt') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsStepAdminAt DATETIME2(0) NULL;
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsStepSharedCatalogDone') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsStepSharedCatalogDone BIT NOT NULL
        CONSTRAINT DF_ProviderSignupApplication_AccsStepSharedCatalogDone DEFAULT (0);
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsStepSharedCatalogAt') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsStepSharedCatalogAt DATETIME2(0) NULL;
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsSharedCatalogId') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsSharedCatalogId INT NULL;
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsStepCatalogAssignDone') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsStepCatalogAssignDone BIT NOT NULL
        CONSTRAINT DF_ProviderSignupApplication_AccsStepCatalogAssignDone DEFAULT (0);
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsStepCatalogAssignAt') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsStepCatalogAssignAt DATETIME2(0) NULL;
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsCatalogCategoryCount') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsCatalogCategoryCount INT NULL;
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsCatalogProductCount') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsCatalogProductCount INT NULL;
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsStepRolesDone') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsStepRolesDone BIT NOT NULL
        CONSTRAINT DF_ProviderSignupApplication_AccsStepRolesDone DEFAULT (0);
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsStepRolesAt') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsStepRolesAt DATETIME2(0) NULL;
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsRolesSummary') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsRolesSummary NVARCHAR(500) NULL;
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsConfigurationComplete') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsConfigurationComplete BIT NOT NULL
        CONSTRAINT DF_ProviderSignupApplication_AccsConfigurationComplete DEFAULT (0);
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsConfigurationCompletedAt') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsConfigurationCompletedAt DATETIME2(0) NULL;
GO

-- Backfill clinic + admin steps for applications already provisioned in ACCS.
UPDATE dbo.ProviderSignupApplication
SET AccsStepClinicDone = 1,
    AccsStepClinicAt = COALESCE(AccsStepClinicAt, ProvisionedAt, LastSavedAt),
    AccsStepAdminDone = 1,
    AccsStepAdminAt = COALESCE(AccsStepAdminAt, ProvisionedAt, LastSavedAt)
WHERE AccsCompanyId IS NOT NULL
  AND AccsCustomerId IS NOT NULL
  AND (AccsStepClinicDone = 0 OR AccsStepAdminDone = 0);
GO
