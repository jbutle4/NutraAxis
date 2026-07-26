-- Rename provider application status "Submitted" → "Submitted for Review".
IF OBJECT_ID(N'dbo.CK_ProviderSignupApplication_Status', N'C') IS NOT NULL
    ALTER TABLE dbo.ProviderSignupApplication DROP CONSTRAINT CK_ProviderSignupApplication_Status;
GO

UPDATE dbo.ProviderSignupApplication
SET Status = N'Submitted for Review'
WHERE Status = N'Submitted';
GO

ALTER TABLE dbo.ProviderSignupApplication
ADD CONSTRAINT CK_ProviderSignupApplication_Status CHECK (
    Status IN (
        N'Draft',
        N'Submitted for Review',
        N'Returned',
        N'Pending Validation',
        N'Approved',
        N'Provisioned',
        N'Rejected'
    )
);
GO
