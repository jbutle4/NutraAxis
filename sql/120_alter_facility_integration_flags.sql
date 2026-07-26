/*
  NutraAxis Operations — Facility integration flags (mothership, PO receipt, Jazz vs local)
*/

IF COL_LENGTH('dbo.Facility', 'IsMothership') IS NULL
    ALTER TABLE dbo.Facility
        ADD IsMothership BIT NOT NULL
            CONSTRAINT DF_Facility_IsMothership DEFAULT (0);

IF COL_LENGTH('dbo.Facility', 'ReceivesPurchaseOrders') IS NULL
    ALTER TABLE dbo.Facility
        ADD ReceivesPurchaseOrders BIT NOT NULL
            CONSTRAINT DF_Facility_ReceivesPO DEFAULT (0);

IF COL_LENGTH('dbo.Facility', 'IntegrationMode') IS NULL
    ALTER TABLE dbo.Facility
        ADD IntegrationMode NVARCHAR(20) NOT NULL
            CONSTRAINT DF_Facility_IntegrationMode DEFAULT (N'Local');

IF COL_LENGTH('dbo.Facility', 'ExternalReferenceCode') IS NULL
    ALTER TABLE dbo.Facility
        ADD ExternalReferenceCode NVARCHAR(50) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Facility_IntegrationMode', N'C') IS NULL
    ALTER TABLE dbo.Facility
        ADD CONSTRAINT CK_Facility_IntegrationMode
        CHECK (IntegrationMode IN (N'Local', N'Jazz'));
GO

UPDATE dbo.Facility
SET
    IsMothership           = 1,
    ReceivesPurchaseOrders = 1,
    IntegrationMode        = N'Jazz'
WHERE FacilityCode = N'CART';
GO

UPDATE dbo.Facility
SET
    IsMothership           = 0,
    ReceivesPurchaseOrders = 0,
    IntegrationMode        = N'Local'
WHERE FacilityCode IN (N'CPPC', N'WLO');
GO

UPDATE dbo.Facility
SET
    IsMothership           = 0,
    ReceivesPurchaseOrders = 0,
    IntegrationMode        = N'Local'
WHERE FacilityCode = N'TRANSIT';
GO
