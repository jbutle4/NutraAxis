/*
  NutraAxis Operations — POPayment: payment-made tracking fields

  Note: PaymentAmount (decimal) and PaymentConfNumber (nvarchar) already exist on
  POPayment for the scheduled/recorded payment. These columns capture values when
  payment is actually made (date, amount, confirmation #, notes).
*/

IF COL_LENGTH('dbo.POPayment', 'PaymentMadeDate') IS NULL
    ALTER TABLE dbo.POPayment ADD PaymentMadeDate DATE NULL;
GO

IF COL_LENGTH('dbo.POPayment', 'PaymentMadeAmount') IS NULL
    ALTER TABLE dbo.POPayment ADD PaymentMadeAmount DECIMAL(18, 2) NULL;
GO

IF COL_LENGTH('dbo.POPayment', 'PaymentMadeConfirmationNumber') IS NULL
    ALTER TABLE dbo.POPayment ADD PaymentMadeConfirmationNumber NVARCHAR(100) NULL;
GO

IF COL_LENGTH('dbo.POPayment', 'PaymentMadeNotes') IS NULL
    ALTER TABLE dbo.POPayment ADD PaymentMadeNotes NVARCHAR(MAX) NULL;
GO
