/*
  Ship-to address on Facility — used for Jazz order import on CART → pharmacy transfers.
*/

IF COL_LENGTH('dbo.Facility', 'ShipAddress1') IS NULL
    ALTER TABLE dbo.Facility ADD ShipAddress1 NVARCHAR(180) NULL;

IF COL_LENGTH('dbo.Facility', 'ShipAddress2') IS NULL
    ALTER TABLE dbo.Facility ADD ShipAddress2 NVARCHAR(180) NULL;

IF COL_LENGTH('dbo.Facility', 'ShipCity') IS NULL
    ALTER TABLE dbo.Facility ADD ShipCity NVARCHAR(90) NULL;

IF COL_LENGTH('dbo.Facility', 'ShipState') IS NULL
    ALTER TABLE dbo.Facility ADD ShipState NVARCHAR(20) NULL;

IF COL_LENGTH('dbo.Facility', 'ShipPostalCode') IS NULL
    ALTER TABLE dbo.Facility ADD ShipPostalCode NVARCHAR(20) NULL;

IF COL_LENGTH('dbo.Facility', 'ShipCountry') IS NULL
    ALTER TABLE dbo.Facility ADD ShipCountry NVARCHAR(2) NULL
        CONSTRAINT DF_Facility_ShipCountry DEFAULT (N'US');

IF COL_LENGTH('dbo.Facility', 'ShipAttnFirstName') IS NULL
    ALTER TABLE dbo.Facility ADD ShipAttnFirstName NVARCHAR(90) NULL;

IF COL_LENGTH('dbo.Facility', 'ShipAttnLastName') IS NULL
    ALTER TABLE dbo.Facility ADD ShipAttnLastName NVARCHAR(90) NULL;

IF COL_LENGTH('dbo.Facility', 'ShipCompany') IS NULL
    ALTER TABLE dbo.Facility ADD ShipCompany NVARCHAR(180) NULL;

IF COL_LENGTH('dbo.Facility', 'ShipPhone') IS NULL
    ALTER TABLE dbo.Facility ADD ShipPhone NVARCHAR(30) NULL;

IF COL_LENGTH('dbo.Facility', 'ShipEmail') IS NULL
    ALTER TABLE dbo.Facility ADD ShipEmail NVARCHAR(254) NULL;

GO

UPDATE dbo.Facility
SET
    FacilityName = N'Melbourne Pharmacy',
    ShipCompany = N'Melbourne Pharmacy',
    ShipAddress1 = N'2225 South Babcock Street',
    ShipAddress2 = N'Suite #107',
    ShipCity = N'Melbourne',
    ShipState = N'FL',
    ShipPostalCode = N'32901',
    ShipCountry = N'US',
    ShipAttnFirstName = N'Connie',
    ShipAttnLastName = N'Torres',
    ShipPhone = N'833-655-7500',
    ShipEmail = N'Ctorres@melbournepharma.com',
    ModifiedDate = SYSUTCDATETIME()
WHERE FacilityCode = N'MELBOURNE';

UPDATE dbo.Facility
SET
    FacilityName = N'Wells Pharmacy Network Ocala',
    ShipCompany = N'Wells Pharmacy Network, LLC',
    ShipAddress1 = N'1210 SW 33rd Avenue',
    ShipAddress2 = NULL,
    ShipCity = N'Ocala',
    ShipState = N'FL',
    ShipPostalCode = N'34474',
    ShipCountry = N'US',
    ShipAttnFirstName = N'Christopher',
    ShipAttnLastName = N'Ulbricht',
    ShipPhone = N'352-622-2913',
    ShipEmail = N'CUlbricht@wellsrx.com',
    ModifiedDate = SYSUTCDATETIME()
WHERE FacilityCode = N'OCALA';

UPDATE dbo.Facility
SET
    FacilityName = N'Wells Pharma Labs - Dallas',
    ShipCompany = N'Wells Pharma Labs',
    ShipAddress1 = N'4001 McEwen Rd',
    ShipAddress2 = N'Suite 110',
    ShipCity = N'Dallas',
    ShipState = N'TX',
    ShipPostalCode = N'75244',
    ShipCountry = N'US',
    ShipAttnFirstName = N'Michael',
    ShipAttnLastName = N'Flora',
    ShipPhone = N'800-441-0048',
    ShipEmail = N'mflora@wppharmalabs.com',
    ModifiedDate = SYSUTCDATETIME()
WHERE FacilityCode = N'DALLAS';

GO