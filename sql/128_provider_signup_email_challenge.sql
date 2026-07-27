-- Provider signup email ownership challenge.
-- Applications are created only after the provider clicks the emailed link.

IF OBJECT_ID(N'dbo.ProviderSignupEmailChallenge', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ProviderSignupEmailChallenge (
        ChallengeID     INT             NOT NULL IDENTITY(1, 1),
        ChallengeToken  CHAR(64)        NOT NULL,
        ProviderEmail   NVARCHAR(255)   NOT NULL,
        RequestIp       NVARCHAR(64)    NULL,
        CreatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_ProviderSignupEmailChallenge_CreatedAt DEFAULT (SYSUTCDATETIME()),
        ExpiresAt       DATETIME2(0)    NOT NULL,
        ConsumedAt      DATETIME2(0)    NULL,
        CONSTRAINT PK_ProviderSignupEmailChallenge PRIMARY KEY CLUSTERED (ChallengeID),
        CONSTRAINT UQ_ProviderSignupEmailChallenge_Token UNIQUE (ChallengeToken)
    );

    CREATE NONCLUSTERED INDEX IX_ProviderSignupEmailChallenge_Email_Created
        ON dbo.ProviderSignupEmailChallenge (ProviderEmail, CreatedAt DESC);

    CREATE NONCLUSTERED INDEX IX_ProviderSignupEmailChallenge_Ip_Created
        ON dbo.ProviderSignupEmailChallenge (RequestIp, CreatedAt DESC);
END
GO
