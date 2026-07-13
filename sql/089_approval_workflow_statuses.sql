/*
  NutraAxis Operations — Approval workflow status values for supplier invoices and payments
*/

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = N'CK_SupplierInvoice_SyncStatus')
    ALTER TABLE dbo.SupplierInvoice DROP CONSTRAINT CK_SupplierInvoice_SyncStatus;
GO

UPDATE dbo.SupplierInvoice
SET SyncStatus = N'Submitted for Approval'
WHERE SyncStatus = N'Ready';
GO

ALTER TABLE dbo.SupplierInvoice
    ADD CONSTRAINT CK_SupplierInvoice_SyncStatus CHECK (
        SyncStatus IN (
            N'Draft',
            N'Submitted for Approval',
            N'Sent Back for Comment',
            N'Rejected',
            N'Posted',
            N'Failed',
            N'Voided'
        )
    );
GO

IF OBJECT_ID(N'dbo.CK_POPayment_PaymentStatus', N'C') IS NOT NULL
    ALTER TABLE dbo.POPayment DROP CONSTRAINT CK_POPayment_PaymentStatus;
GO

ALTER TABLE dbo.POPayment
    ADD CONSTRAINT CK_POPayment_PaymentStatus CHECK (
        PaymentStatus IS NULL
        OR PaymentStatus IN (
            N'Pending',
            N'Submitted for Approval',
            N'Sent Back for Comment',
            N'Transmitted to QBO',
            N'Paid',
            N'Failed',
            N'Cancelled'
        )
    );
GO
