/*
  NutraAxis Operations — Legal Agreements contract register
*/

IF OBJECT_ID(N'dbo.ContractRegister', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ContractRegister (
        ContractID              INT             NOT NULL IDENTITY(1,1),
        ContractNumber          NVARCHAR(20)    NOT NULL,
        ContractName            NVARCHAR(300)   NOT NULL,
        Counterparty            NVARCHAR(300)   NOT NULL,
        ContractType            NVARCHAR(50)    NOT NULL,
        ContractStatus          NVARCHAR(30)    NOT NULL,
        EffectiveDate           DATE            NULL,
        ExpirationDate          DATE            NULL,
        ExpirationNotes         NVARCHAR(100)   NULL,
        AutoRenewal             BIT             NOT NULL CONSTRAINT DF_ContractRegister_AutoRenewal DEFAULT (0),
        RenewalNoticeDays       INT             NULL,
        AnnualValue             DECIMAL(18,2)   NULL,
        InternalOwnerUser       INT             NULL,
        ExternalSignatory       NVARCHAR(200)   NULL,
        RelatedSupplier         NVARCHAR(200)   NULL,
        GoverningLaw            NVARCHAR(50)    NULL,
        ConfidentialityMonths   INT             NULL,
        KeyObligationsSummary   NVARCHAR(MAX)   NULL,
        DocumentLink            NVARCHAR(2000)  NULL,
        AmendmentLinks          NVARCHAR(MAX)   NULL,
        Notes                   NVARCHAR(MAX)   NULL,
        CreateDate              DATETIME2(0)    NOT NULL CONSTRAINT DF_ContractRegister_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate            DATETIME2(0)    NOT NULL CONSTRAINT DF_ContractRegister_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedbyUser          INT             NULL,

        CONSTRAINT PK_ContractRegister PRIMARY KEY CLUSTERED (ContractID),
        CONSTRAINT UQ_ContractRegister_ContractNumber UNIQUE (ContractNumber),
        CONSTRAINT FK_ContractRegister_InternalOwnerUser FOREIGN KEY (InternalOwnerUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_ContractRegister_ModifiedByUser FOREIGN KEY (ModifiedbyUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT CK_ContractRegister_ContractType CHECK (
            ContractType IN (
                N'NDA/MNDA',
                N'Manufacturing Agreement',
                N'Quality Agreement',
                N'Supply Agreement',
                N'SOW / Consulting',
                N'MSA',
                N'TMLA',
                N'Partnership',
                N'Distribution',
                N'Tax Service',
                N'Lab Services',
                N'Other'
            )
        ),
        CONSTRAINT CK_ContractRegister_ContractStatus CHECK (
            ContractStatus IN (
                N'Draft',
                N'In Review',
                N'Under Negotiation',
                N'Executed',
                N'Active',
                N'Expired',
                N'Terminated'
            )
        ),
        CONSTRAINT CK_ContractRegister_RenewalNoticeDays CHECK (
            RenewalNoticeDays IS NULL OR RenewalNoticeDays >= 0
        ),
        CONSTRAINT CK_ContractRegister_AnnualValue CHECK (
            AnnualValue IS NULL OR AnnualValue >= 0
        ),
        CONSTRAINT CK_ContractRegister_ConfidentialityMonths CHECK (
            ConfidentialityMonths IS NULL OR ConfidentialityMonths >= 0
        )
    );

    CREATE NONCLUSTERED INDEX IX_ContractRegister_ContractStatus
        ON dbo.ContractRegister (ContractStatus);

    CREATE NONCLUSTERED INDEX IX_ContractRegister_ExpirationDate
        ON dbo.ContractRegister (ExpirationDate);

    CREATE NONCLUSTERED INDEX IX_ContractRegister_Counterparty
        ON dbo.ContractRegister (Counterparty);
END;
GO
