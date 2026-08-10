/*
  Backfill ACCS environment for clinics provisioned before AccsEnvironment tracking.
*/

UPDATE dbo.ProviderSignupApplication
SET AccsEnvironment = 'production'
WHERE AccsEnvironment IS NULL
  AND AccsCompanyId IS NOT NULL;
GO
