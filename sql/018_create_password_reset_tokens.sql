/*
  NutraAxis Operations — self-service password reset tokens
*/

IF OBJECT_ID(N'dbo.PasswordResetToken', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.PasswordResetToken (
        TokenID      INT             NOT NULL IDENTITY(1,1),
        UserID       INT             NOT NULL,
        TokenHash    CHAR(64)        NOT NULL,
        ExpiresAt    DATETIME2(0)    NOT NULL,
        UsedAt       DATETIME2(0)    NULL,
        CreatedAt    DATETIME2(0)    NOT NULL CONSTRAINT DF_PasswordResetToken_CreatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_PasswordResetToken PRIMARY KEY CLUSTERED (TokenID),
        CONSTRAINT FK_PasswordResetToken_User FOREIGN KEY (UserID)
            REFERENCES dbo.[User] (UserID) ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX IX_PasswordResetToken_TokenHash
        ON dbo.PasswordResetToken (TokenHash)
        WHERE UsedAt IS NULL;

    CREATE NONCLUSTERED INDEX IX_PasswordResetToken_UserID
        ON dbo.PasswordResetToken (UserID, CreatedAt DESC);
END;
GO
