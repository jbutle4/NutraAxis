/*
  NutraAxis Operations — PO Receipt shipping fields and POR detail barcodes
*/

IF COL_LENGTH('dbo.POReceipt', 'BusinessType') IS NULL
    ALTER TABLE dbo.POReceipt ADD BusinessType NVARCHAR(100) NULL;
GO

IF COL_LENGTH('dbo.POReceipt', 'ShipmentNumber') IS NULL
    ALTER TABLE dbo.POReceipt ADD ShipmentNumber NVARCHAR(100) NULL;
GO

IF COL_LENGTH('dbo.POReceipt', 'Facility') IS NULL
    ALTER TABLE dbo.POReceipt ADD Facility NVARCHAR(100) NULL;
GO

IF COL_LENGTH('dbo.POReceipt', 'CarrierNumber') IS NULL
    ALTER TABLE dbo.POReceipt ADD CarrierNumber NVARCHAR(100) NULL;
GO

IF COL_LENGTH('dbo.POReceipt', 'SealNumber') IS NULL
    ALTER TABLE dbo.POReceipt ADD SealNumber NVARCHAR(100) NULL;
GO

IF COL_LENGTH('dbo.POReceipt', 'LoadNumber') IS NULL
    ALTER TABLE dbo.POReceipt ADD LoadNumber NVARCHAR(100) NULL;
GO

IF COL_LENGTH('dbo.POReceipt', 'ShippingMethod') IS NULL
    ALTER TABLE dbo.POReceipt ADD ShippingMethod NVARCHAR(100) NULL;
GO

IF COL_LENGTH('dbo.POReceipt', 'ShippedAt') IS NULL
    ALTER TABLE dbo.POReceipt ADD ShippedAt DATETIME2(0) NULL;
GO

IF COL_LENGTH('dbo.PORDetail', 'CaseBarcode') IS NULL
    ALTER TABLE dbo.PORDetail ADD CaseBarcode NVARCHAR(100) NULL;
GO

IF COL_LENGTH('dbo.PORDetail', 'SKUBarcode') IS NULL
    ALTER TABLE dbo.PORDetail ADD SKUBarcode NVARCHAR(100) NULL;
GO

IF COL_LENGTH('dbo.PORDetail', 'CountryOfOrigin') IS NULL
    ALTER TABLE dbo.PORDetail ADD CountryOfOrigin NVARCHAR(100) NULL;
GO

IF COL_LENGTH('dbo.PORDetail', 'OnHold') IS NULL
    ALTER TABLE dbo.PORDetail ADD OnHold BIT NOT NULL CONSTRAINT DF_PORDetail_OnHold DEFAULT (0);
GO
