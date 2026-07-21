/*
  Provider signup — Practitioner Reseller Policy acknowledgement tracking.
*/

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'PolicyAcknowledgedAt') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD PolicyAcknowledgedAt DATETIME2(0) NULL;
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'PolicyAcknowledgedByEmail') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD PolicyAcknowledgedByEmail NVARCHAR(255) NULL;
GO

IF COL_LENGTH(N'dbo.ProviderSignupApplication', N'PolicyVersion') IS NULL
    ALTER TABLE dbo.ProviderSignupApplication ADD PolicyVersion NVARCHAR(32) NULL;
GO

IF OBJECT_ID(N'dbo.CK_ProviderSignupReviewLog_Action', N'C') IS NOT NULL
    ALTER TABLE dbo.ProviderSignupReviewLog DROP CONSTRAINT CK_ProviderSignupReviewLog_Action;
GO

ALTER TABLE dbo.ProviderSignupReviewLog
ADD CONSTRAINT CK_ProviderSignupReviewLog_Action CHECK (
    ReviewAction IN (
        N'Submitted',
        N'Comment',
        N'Returned',
        N'Reopened',
        N'Approved',
        N'Rejected',
        N'Updated',
        N'Activated',
        N'NpiValidated',
        N'BankingValidated',
        N'Provisioned',
        N'ProvisionFailed',
        N'PolicyAcknowledged'
    )
);
GO
