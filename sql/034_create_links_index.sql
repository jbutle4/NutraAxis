/*
  NutraAxis Operations — Links Index
*/

IF OBJECT_ID(N'dbo.LinksIndex', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.LinksIndex (
        LinkID                      INT             NOT NULL IDENTITY(1,1),
        LinkName                    NVARCHAR(200)   NOT NULL,
        LinkDescription             NVARCHAR(500)   NULL,
        LinkCategory                NVARCHAR(60)    NOT NULL,
        LinkStatus                  NVARCHAR(20)    NOT NULL CONSTRAINT DF_LinksIndex_Status DEFAULT (N'active'),
        UserRegistrationRequired    BIT             NOT NULL CONSTRAINT DF_LinksIndex_UserRegistration DEFAULT (0),
        LinkURL                     NVARCHAR(2000)  NOT NULL,
        CreateDate                  DATETIME2(0)    NOT NULL CONSTRAINT DF_LinksIndex_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate                DATETIME2(0)    NOT NULL CONSTRAINT DF_LinksIndex_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedbyUser              INT             NULL,

        CONSTRAINT PK_LinksIndex PRIMARY KEY CLUSTERED (LinkID),
        CONSTRAINT FK_LinksIndex_ModifiedByUser FOREIGN KEY (ModifiedbyUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT CK_LinksIndex_Category CHECK (
            LinkCategory IN (
                N'Web application',
                N'MS365 Application',
                N'Document',
                N'External Website - Reference',
                N'Other'
            )
        ),
        CONSTRAINT CK_LinksIndex_Status CHECK (
            LinkStatus IN (N'active', N'not active')
        )
    );
END;
GO
