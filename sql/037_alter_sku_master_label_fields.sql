/*
  NutraAxis Operations — SKU Master label / formulation fields
*/

IF COL_LENGTH('dbo.SKUMaster', 'Formulation') IS NULL
    ALTER TABLE dbo.SKUMaster ADD Formulation NVARCHAR(MAX) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'Product') IS NULL
    ALTER TABLE dbo.SKUMaster ADD Product NVARCHAR(MAX) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'LabelSelection') IS NULL
    ALTER TABLE dbo.SKUMaster ADD LabelSelection NVARCHAR(100) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'Directions') IS NULL
    ALTER TABLE dbo.SKUMaster ADD Directions NVARCHAR(MAX) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'CapsuleCount') IS NULL
    ALTER TABLE dbo.SKUMaster ADD CapsuleCount INT NULL;

IF COL_LENGTH('dbo.SKUMaster', 'CertsOnLabel') IS NULL
    ALTER TABLE dbo.SKUMaster ADD CertsOnLabel NVARCHAR(MAX) NULL;
GO

IF OBJECT_ID(N'dbo.CK_SKUMaster_CapsuleCount', N'C') IS NULL
BEGIN
    ALTER TABLE dbo.SKUMaster
        ADD CONSTRAINT CK_SKUMaster_CapsuleCount CHECK (
            CapsuleCount IS NULL OR CapsuleCount > 0
        );
END;
GO
