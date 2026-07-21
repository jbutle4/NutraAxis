/*
  NutraAxis Operations — SKU Master recommended products and website description
*/

IF COL_LENGTH('dbo.SKUMaster', 'RecommendedProducts') IS NULL
    ALTER TABLE dbo.SKUMaster ADD RecommendedProducts NVARCHAR(MAX) NULL;
GO

IF COL_LENGTH('dbo.SKUMaster', 'WebsiteProductDescription') IS NULL
    ALTER TABLE dbo.SKUMaster ADD WebsiteProductDescription NVARCHAR(MAX) NULL;
GO
