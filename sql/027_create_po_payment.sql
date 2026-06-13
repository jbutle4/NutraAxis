/*
  NutraAxis Operations — PO Payments
*/

IF OBJECT_ID(N'dbo.POPayment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.POPayment (
        PaymentID           INT             NOT NULL IDENTITY(1,1),
        POID                INT             NOT NULL,
        PaymentDate         DATETIME2(0)    NOT NULL,
        PaymentAmount       DECIMAL(18,2)   NOT NULL,
        PaymentType         NVARCHAR(20)    NOT NULL,
        PaymentConfNumber   NVARCHAR(100)   NULL,
        PaymentMadeBy       NVARCHAR(200)   NULL,
        PaymentComments     NVARCHAR(MAX)   NULL,
        CreatedByUser       INT             NULL,
        CreateDate          DATETIME2(0)    NOT NULL CONSTRAINT DF_POPayment_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate        DATETIME2(0)    NOT NULL CONSTRAINT DF_POPayment_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedbyUser      INT             NULL,

        CONSTRAINT PK_POPayment PRIMARY KEY CLUSTERED (PaymentID),
        CONSTRAINT FK_POPayment_PurchaseOrder FOREIGN KEY (POID)
            REFERENCES dbo.PurchaseOrder (POID),
        CONSTRAINT FK_POPayment_CreatedByUser FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_POPayment_ModifiedByUser FOREIGN KEY (ModifiedbyUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT CK_POPayment_PaymentType CHECK (
            PaymentType IN (N'Check', N'ACH', N'CC')
        ),
        CONSTRAINT CK_POPayment_PaymentAmount CHECK (
            PaymentAmount > 0
        )
    );

    CREATE NONCLUSTERED INDEX IX_POPayment_POID
        ON dbo.POPayment (POID);

    CREATE NONCLUSTERED INDEX IX_POPayment_PaymentDate
        ON dbo.POPayment (PaymentDate DESC);
END;
GO
