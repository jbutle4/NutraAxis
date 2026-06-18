/*
  NutraAxis Operations — Contacts List
*/

IF OBJECT_ID(N'dbo.ContactsList', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ContactsList (
        ContactID               INT             NOT NULL IDENTITY(1,1),
        ContactFirstName        NVARCHAR(100)   NULL,
        ContactLastName         NVARCHAR(100)   NULL,
        ContactCompany          NVARCHAR(200)   NULL,
        RelatedSupplierCompany  INT             NULL,
        ContactType             NVARCHAR(40)    NULL,
        ContactPhone            NVARCHAR(50)    NULL,
        ContactEmail            NVARCHAR(200)   NULL,
        ContactAddress          NVARCHAR(500)   NULL,
        ContactCity             NVARCHAR(100)   NULL,
        ContactState            NVARCHAR(2)     NULL,
        ContactZip              NVARCHAR(20)    NULL,
        ContactNotes            NVARCHAR(MAX)   NULL,
        CreateDate              DATETIME2(0)    NOT NULL CONSTRAINT DF_ContactsList_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate            DATETIME2(0)    NOT NULL CONSTRAINT DF_ContactsList_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedbyUser          INT             NULL,

        CONSTRAINT PK_ContactsList PRIMARY KEY CLUSTERED (ContactID),
        CONSTRAINT FK_ContactsList_RelatedSupplier FOREIGN KEY (RelatedSupplierCompany)
            REFERENCES dbo.Supplier (SupplierID),
        CONSTRAINT FK_ContactsList_ModifiedByUser FOREIGN KEY (ModifiedbyUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT CK_ContactsList_ContactType CHECK (
            ContactType IS NULL OR ContactType IN (
                N'supplier',
                N'contractor',
                N'education',
                N'marketing',
                N'other'
            )
        )
    );

    CREATE NONCLUSTERED INDEX IX_ContactsList_RelatedSupplierCompany
        ON dbo.ContactsList (RelatedSupplierCompany);

    CREATE NONCLUSTERED INDEX IX_ContactsList_ContactType
        ON dbo.ContactsList (ContactType);
END;
GO
