/*
  NutraAxis Operations — SKU Master product weights (lbs)
*/

IF COL_LENGTH('dbo.SKUMaster', 'ProductEachWeightLbs') IS NULL
    ALTER TABLE dbo.SKUMaster ADD ProductEachWeightLbs FLOAT NULL;

IF COL_LENGTH('dbo.SKUMaster', 'ProductCaseWeightLbs') IS NULL
    ALTER TABLE dbo.SKUMaster ADD ProductCaseWeightLbs FLOAT NULL;
GO
