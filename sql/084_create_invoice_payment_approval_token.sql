/*
  NutraAxis Operations — Email approval tokens for supplier invoice payments
*/

IF OBJECT_ID(N'dbo.InvoicePaymentApprovalToken', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InvoicePaymentApprovalToken (
        TokenID             INT             NOT NULL IDENTITY(1,1),
        PaymentID           INT             NOT NULL,
        SupplierInvoiceID   INT             NOT NULL,
        UserID              INT             NOT NULL,
        TokenHash           CHAR(64)        NOT NULL,
        ExpiresAt           DATETIME2(0)    NOT NULL,
        UsedAt              DATETIME2(0)    NULL,
        CreatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_InvoicePaymentApprovalToken_CreatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_InvoicePaymentApprovalToken PRIMARY KEY CLUSTERED (TokenID),
        CONSTRAINT FK_InvoicePaymentApprovalToken_Payment FOREIGN KEY (PaymentID)
            REFERENCES dbo.POPayment (PaymentID) ON DELETE CASCADE,
        CONSTRAINT FK_InvoicePaymentApprovalToken_SupplierInvoice FOREIGN KEY (SupplierInvoiceID)
            REFERENCES dbo.SupplierInvoice (SupplierInvoiceID),
        CONSTRAINT FK_InvoicePaymentApprovalToken_User FOREIGN KEY (UserID)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_InvoicePaymentApprovalToken_PaymentID
        ON dbo.InvoicePaymentApprovalToken (PaymentID, ExpiresAt DESC);

    CREATE NONCLUSTERED INDEX IX_InvoicePaymentApprovalToken_SupplierInvoiceID
        ON dbo.InvoicePaymentApprovalToken (SupplierInvoiceID, ExpiresAt DESC);

    CREATE NONCLUSTERED INDEX IX_InvoicePaymentApprovalToken_Hash
        ON dbo.InvoicePaymentApprovalToken (TokenHash)
        WHERE UsedAt IS NULL;
END;
GO
