/*
  Allow Deprovisioned review log action when ops removes an ACCS clinic store.
*/

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
        N'PolicyAcknowledged',
        N'Deprovisioned'
    )
);
GO
