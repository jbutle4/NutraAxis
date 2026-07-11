/*
  Provider signup — clinic type (ACCS company custom attribute clinic-type)
*/

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'ClinicType') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD ClinicType NVARCHAR(255) NULL;
GO
