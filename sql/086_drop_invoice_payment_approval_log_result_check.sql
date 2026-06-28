/*
  NutraAxis Operations — Drop ApproverResult CHECK on InvoicePaymentApprovalLog (match PO/TE logs)
*/

IF OBJECT_ID(N'dbo.FK_InvoicePaymentApprovalLog_ApproverResult', N'C') IS NOT NULL
    ALTER TABLE dbo.InvoicePaymentApprovalLog
        DROP CONSTRAINT FK_InvoicePaymentApprovalLog_ApproverResult;
GO

IF OBJECT_ID(N'dbo.CK_InvoicePaymentApprovalLog_ApproverResult', N'C') IS NOT NULL
    ALTER TABLE dbo.InvoicePaymentApprovalLog
        DROP CONSTRAINT CK_InvoicePaymentApprovalLog_ApproverResult;
GO
