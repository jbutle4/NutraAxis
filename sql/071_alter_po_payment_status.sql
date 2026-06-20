/*
  NutraAxis Operations — PO Payment status
*/

IF COL_LENGTH('dbo.POPayment', 'PaymentStatus') IS NULL
    ALTER TABLE dbo.POPayment ADD PaymentStatus NVARCHAR(30) NULL;
GO
