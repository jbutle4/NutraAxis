/*
  Provider signup — record which ACCS tenant a clinic was provisioned against.
*/

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'AccsEnvironment') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD AccsEnvironment NVARCHAR(20) NULL;
GO
