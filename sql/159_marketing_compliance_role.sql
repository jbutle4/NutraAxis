/*
  NutraAxis Operations — Marketing Compliance Review permission and role

  Compliance review in Campaign Studio and the Content Pipeline is granted by the role permission
  Role.MarketingCompliance (Update = may clear or return compliance) instead of the review.compliance_reviewers
  login roster. Any role can carry it; the "Marketing Compliance Reviewer" role is the preset for the compliance
  officer (Marketing read/update so they can open and act on campaigns and content).

  Reverse:
    DELETE FROM dbo.Role WHERE RoleName = N'Marketing Compliance Reviewer';  -- only if no users hold it
    ALTER TABLE dbo.Role DROP CONSTRAINT CK_Role_MarketingCompliance_CRUD;
    ALTER TABLE dbo.Role DROP COLUMN MarketingCompliance;
    INSERT INTO dbo.MktSetting (SettingKey, SettingValue, Description) VALUES (N'review.compliance_reviewers', N'', N'…');
*/

IF COL_LENGTH(N'dbo.Role', N'MarketingCompliance') IS NULL
    ALTER TABLE dbo.Role ADD MarketingCompliance NVARCHAR(10) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Role_MarketingCompliance_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
    ADD CONSTRAINT CK_Role_MarketingCompliance_CRUD
    CHECK (MarketingCompliance IS NULL OR MarketingCompliance IN (
        N'C', N'R', N'U', N'D',
        N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
        N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
    ));
GO

IF NOT EXISTS (SELECT 1 FROM dbo.Role WHERE RoleName = N'Marketing Compliance Reviewer')
    INSERT INTO dbo.Role (RoleName, RoleDesc, RoleCreateDate, Marketing, MarketingCompliance)
    VALUES (
        N'Marketing Compliance Reviewer',
        N'Compliance officer for marketing: reviews campaign assets and long-form content against the approved Claims Matrix and clears or returns them before editorial approval. Marketing read/update; no admin or spend access.',
        SYSUTCDATETIME(), N'RU', N'RU'
    );
GO

DELETE FROM dbo.MktSetting WHERE SettingKey = N'review.compliance_reviewers';
GO
