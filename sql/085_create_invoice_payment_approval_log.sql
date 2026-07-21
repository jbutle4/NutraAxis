/*
  NutraAxis Operations — Approval action log for supplier invoice payments
*/

IF OBJECT_ID(N'dbo.InvoicePaymentApprovalLog', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.InvoicePaymentApprovalLog (
        ApprovalID          INT             NOT NULL IDENTITY(1,1),
        PaymentID           INT             NOT NULL,
        SupplierInvoiceID   INT             NOT NULL,
        ApproverName        NVARCHAR(200)   NOT NULL,
        ApproverResult      NVARCHAR(100)   NOT NULL,
        ApproverComments    NVARCHAR(MAX)   NULL,
        LogDate             DATETIME2(0)    NOT NULL CONSTRAINT DF_InvoicePaymentApprovalLog_LogDate DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_InvoicePaymentApprovalLog PRIMARY KEY CLUSTERED (ApprovalID),
        CONSTRAINT FK_InvoicePaymentApprovalLog_Payment FOREIGN KEY (PaymentID)
            REFERENCES dbo.POPayment (PaymentID) ON DELETE CASCADE,
        CONSTRAINT FK_InvoicePaymentApprovalLog_SupplierInvoice FOREIGN KEY (SupplierInvoiceID)
            REFERENCES dbo.SupplierInvoice (SupplierInvoiceID)
    );

    CREATE NONCLUSTERED INDEX IX_InvoicePaymentApprovalLog_PaymentID
        ON dbo.InvoicePaymentApprovalLog (PaymentID, LogDate DESC);

    CREATE NONCLUSTERED INDEX IX_InvoicePaymentApprovalLog_SupplierInvoiceID
        ON dbo.InvoicePaymentApprovalLog (SupplierInvoiceID, LogDate DESC);
END;
GO
