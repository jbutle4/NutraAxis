/*
  NutraAxis Operations — QuickBooks Online Chart of Accounts cache (QBO_COA)
*/

IF OBJECT_ID(N'dbo.QBO_COA', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.QBO_COA (
        QBO_COAID                       INT             NOT NULL IDENTITY(1,1),
        RealmID                         NVARCHAR(32)    NOT NULL,
        QBO_AccountId                   NVARCHAR(32)    NOT NULL,
        QBO_SyncToken                   NVARCHAR(32)    NULL,
        Name                            NVARCHAR(255)   NOT NULL,
        AcctNum                         NVARCHAR(50)    NULL,
        FullyQualifiedName              NVARCHAR(500)   NULL,
        AccountType                     NVARCHAR(50)    NULL,
        AccountSubType                  NVARCHAR(100)   NULL,
        Classification                  NVARCHAR(50)    NULL,
        CurrentBalance                  DECIMAL(18,2)   NULL,
        CurrentBalanceWithSubAccounts   DECIMAL(18,2)   NULL,
        Active                          BIT             NOT NULL CONSTRAINT DF_QBO_COA_Active DEFAULT (1),
        Description                     NVARCHAR(1000)  NULL,
        CurrencyRefValue                NVARCHAR(10)    NULL,
        CurrencyRefName                 NVARCHAR(50)    NULL,
        ParentRefValue                  NVARCHAR(32)    NULL,
        ParentRefName                   NVARCHAR(255)   NULL,
        QBO_LastUpdatedAt               DATETIME2(0)    NULL,
        SyncedAt                        DATETIME2(0)    NOT NULL CONSTRAINT DF_QBO_COA_SyncedAt DEFAULT (SYSUTCDATETIME()),
        CreateDate                      DATETIME2(0)    NOT NULL CONSTRAINT DF_QBO_COA_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate                    DATETIME2(0)    NOT NULL CONSTRAINT DF_QBO_COA_ModifiedDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_QBO_COA PRIMARY KEY CLUSTERED (QBO_COAID),
        CONSTRAINT UQ_QBO_COA_Realm_Account UNIQUE (RealmID, QBO_AccountId)
    );

    CREATE NONCLUSTERED INDEX IX_QBO_COA_RealmID
        ON dbo.QBO_COA (RealmID, Active, AccountType);

    CREATE NONCLUSTERED INDEX IX_QBO_COA_AcctNum
        ON dbo.QBO_COA (RealmID, AcctNum)
        WHERE AcctNum IS NOT NULL;

    CREATE NONCLUSTERED INDEX IX_QBO_COA_Name
        ON dbo.QBO_COA (RealmID, Name);
END;
GO
