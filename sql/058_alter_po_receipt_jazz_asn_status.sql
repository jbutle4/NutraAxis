/*
  NutraAxis Operations — PO Receipt Jazz ASN status tracking
*/

IF COL_LENGTH('dbo.POReceipt', 'JazzASNStatus') IS NULL
    ALTER TABLE dbo.POReceipt ADD JazzASNStatus NVARCHAR(30) NULL;
GO

IF COL_LENGTH('dbo.POReceipt', 'JazzASNModifiedDate') IS NULL
    ALTER TABLE dbo.POReceipt ADD JazzASNModifiedDate DATETIME2(0) NULL;
GO
