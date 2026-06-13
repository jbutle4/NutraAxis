/*
  NutraAxis Operations — SKU Master case barcode
*/

IF COL_LENGTH('dbo.SKUMaster', 'SKUCaseBarcode') IS NULL
    ALTER TABLE dbo.SKUMaster ADD SKUCaseBarcode NVARCHAR(100) NULL;
GO
