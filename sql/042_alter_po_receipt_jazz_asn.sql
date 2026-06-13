/*
  NutraAxis Operations — PO Receipt Jazz ASN number
*/

IF COL_LENGTH('dbo.POReceipt', 'JazzASN') IS NULL
    ALTER TABLE dbo.POReceipt ADD JazzASN NVARCHAR(50) NULL;
GO
