/*
  NutraAxis Operations — QuickBooks Online OAuth connection storage
*/

IF OBJECT_ID(N'dbo.QBOConnection', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.QBOConnection (
        ConnectionID          INT             NOT NULL IDENTITY(1,1),
        RealmID                 NVARCHAR(32)    NOT NULL,
        CompanyName             NVARCHAR(255)   NULL,
        AccessToken             NVARCHAR(MAX)   NOT NULL,
        RefreshToken            NVARCHAR(MAX)   NOT NULL,
        AccessTokenExpiresAt    DATETIME2(0)    NOT NULL,
        Environment             NVARCHAR(20)    NOT NULL CONSTRAINT DF_QBOConnection_Environment DEFAULT (N'sandbox'),
        ConnectedByUser         INT             NOT NULL,
        ConnectedAt             DATETIME2(0)    NOT NULL CONSTRAINT DF_QBOConnection_ConnectedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedAt               DATETIME2(0)    NOT NULL CONSTRAINT DF_QBOConnection_UpdatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_QBOConnection PRIMARY KEY CLUSTERED (ConnectionID),
        CONSTRAINT CK_QBOConnection_Environment CHECK (Environment IN (N'sandbox', N'production')),
        CONSTRAINT FK_QBOConnection_ConnectedByUser FOREIGN KEY (ConnectedByUser)
            REFERENCES dbo.[User] (UserID)
    );
END;
GO
