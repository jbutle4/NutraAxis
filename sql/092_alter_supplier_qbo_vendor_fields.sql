/*
  NutraAxis Operations — QuickBooks Online Vendor fields on dbo.Supplier
*/

IF COL_LENGTH('dbo.Supplier', 'QBO_SyncToken') IS NULL
    ALTER TABLE dbo.Supplier ADD QBO_SyncToken NVARCHAR(32) NULL;

IF COL_LENGTH('dbo.Supplier', 'QBO_DisplayName') IS NULL
    ALTER TABLE dbo.Supplier ADD QBO_DisplayName NVARCHAR(200) NULL;

IF COL_LENGTH('dbo.Supplier', 'QBO_SyncedAt') IS NULL
    ALTER TABLE dbo.Supplier ADD QBO_SyncedAt DATETIME2(0) NULL;

IF COL_LENGTH('dbo.Supplier', 'QBO_SyncStatus') IS NULL
    ALTER TABLE dbo.Supplier ADD QBO_SyncStatus NVARCHAR(30) NOT NULL
        CONSTRAINT DF_Supplier_QBO_SyncStatus DEFAULT (N'NotSynced');

IF COL_LENGTH('dbo.Supplier', 'QBO_SyncError') IS NULL
    ALTER TABLE dbo.Supplier ADD QBO_SyncError NVARCHAR(500) NULL;

IF COL_LENGTH('dbo.Supplier', 'CompanyName') IS NULL
    ALTER TABLE dbo.Supplier ADD CompanyName NVARCHAR(200) NULL;

IF COL_LENGTH('dbo.Supplier', 'PrintOnCheckName') IS NULL
    ALTER TABLE dbo.Supplier ADD PrintOnCheckName NVARCHAR(200) NULL;

IF COL_LENGTH('dbo.Supplier', 'Title') IS NULL
    ALTER TABLE dbo.Supplier ADD Title NVARCHAR(16) NULL;

IF COL_LENGTH('dbo.Supplier', 'GivenName') IS NULL
    ALTER TABLE dbo.Supplier ADD GivenName NVARCHAR(100) NULL;

IF COL_LENGTH('dbo.Supplier', 'MiddleName') IS NULL
    ALTER TABLE dbo.Supplier ADD MiddleName NVARCHAR(100) NULL;

IF COL_LENGTH('dbo.Supplier', 'FamilyName') IS NULL
    ALTER TABLE dbo.Supplier ADD FamilyName NVARCHAR(100) NULL;

IF COL_LENGTH('dbo.Supplier', 'Suffix') IS NULL
    ALTER TABLE dbo.Supplier ADD Suffix NVARCHAR(16) NULL;

IF COL_LENGTH('dbo.Supplier', 'BillAddrLine1') IS NULL
    ALTER TABLE dbo.Supplier ADD BillAddrLine1 NVARCHAR(200) NULL;

IF COL_LENGTH('dbo.Supplier', 'BillAddrLine2') IS NULL
    ALTER TABLE dbo.Supplier ADD BillAddrLine2 NVARCHAR(200) NULL;

IF COL_LENGTH('dbo.Supplier', 'BillAddrCity') IS NULL
    ALTER TABLE dbo.Supplier ADD BillAddrCity NVARCHAR(100) NULL;

IF COL_LENGTH('dbo.Supplier', 'BillAddrState') IS NULL
    ALTER TABLE dbo.Supplier ADD BillAddrState NVARCHAR(50) NULL;

IF COL_LENGTH('dbo.Supplier', 'BillAddrPostalCode') IS NULL
    ALTER TABLE dbo.Supplier ADD BillAddrPostalCode NVARCHAR(20) NULL;

IF COL_LENGTH('dbo.Supplier', 'BillAddrCountry') IS NULL
    ALTER TABLE dbo.Supplier ADD BillAddrCountry NVARCHAR(50) NULL
        CONSTRAINT DF_Supplier_BillAddrCountry DEFAULT (N'USA');

IF COL_LENGTH('dbo.Supplier', 'ShipAddrLine1') IS NULL
    ALTER TABLE dbo.Supplier ADD ShipAddrLine1 NVARCHAR(200) NULL;

IF COL_LENGTH('dbo.Supplier', 'ShipAddrLine2') IS NULL
    ALTER TABLE dbo.Supplier ADD ShipAddrLine2 NVARCHAR(200) NULL;

IF COL_LENGTH('dbo.Supplier', 'ShipAddrCity') IS NULL
    ALTER TABLE dbo.Supplier ADD ShipAddrCity NVARCHAR(100) NULL;

IF COL_LENGTH('dbo.Supplier', 'ShipAddrState') IS NULL
    ALTER TABLE dbo.Supplier ADD ShipAddrState NVARCHAR(50) NULL;

IF COL_LENGTH('dbo.Supplier', 'ShipAddrPostalCode') IS NULL
    ALTER TABLE dbo.Supplier ADD ShipAddrPostalCode NVARCHAR(20) NULL;

IF COL_LENGTH('dbo.Supplier', 'ShipAddrCountry') IS NULL
    ALTER TABLE dbo.Supplier ADD ShipAddrCountry NVARCHAR(50) NULL;

IF COL_LENGTH('dbo.Supplier', 'TaxIdentifier') IS NULL
    ALTER TABLE dbo.Supplier ADD TaxIdentifier NVARCHAR(20) NULL;

IF COL_LENGTH('dbo.Supplier', 'Vendor1099') IS NULL
    ALTER TABLE dbo.Supplier ADD Vendor1099 BIT NOT NULL
        CONSTRAINT DF_Supplier_Vendor1099 DEFAULT (0);

IF COL_LENGTH('dbo.Supplier', 'AcctNum') IS NULL
    ALTER TABLE dbo.Supplier ADD AcctNum NVARCHAR(50) NULL;

IF COL_LENGTH('dbo.Supplier', 'TermRefValue') IS NULL
    ALTER TABLE dbo.Supplier ADD TermRefValue NVARCHAR(32) NULL;

IF COL_LENGTH('dbo.Supplier', 'TermRefName') IS NULL
    ALTER TABLE dbo.Supplier ADD TermRefName NVARCHAR(100) NULL;

IF COL_LENGTH('dbo.Supplier', 'WebAddr') IS NULL
    ALTER TABLE dbo.Supplier ADD WebAddr NVARCHAR(500) NULL;

IF COL_LENGTH('dbo.Supplier', 'MobilePhone') IS NULL
    ALTER TABLE dbo.Supplier ADD MobilePhone NVARCHAR(50) NULL;

IF COL_LENGTH('dbo.Supplier', 'AlternatePhone') IS NULL
    ALTER TABLE dbo.Supplier ADD AlternatePhone NVARCHAR(50) NULL;

IF COL_LENGTH('dbo.Supplier', 'FaxPhone') IS NULL
    ALTER TABLE dbo.Supplier ADD FaxPhone NVARCHAR(50) NULL;

IF COL_LENGTH('dbo.Supplier', 'CurrencyRefValue') IS NULL
    ALTER TABLE dbo.Supplier ADD CurrencyRefValue NVARCHAR(32) NULL;

IF COL_LENGTH('dbo.Supplier', 'CurrencyRefName') IS NULL
    ALTER TABLE dbo.Supplier ADD CurrencyRefName NVARCHAR(100) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Supplier_QBO_SyncStatus', N'C') IS NULL
BEGIN
    ALTER TABLE dbo.Supplier
        ADD CONSTRAINT CK_Supplier_QBO_SyncStatus CHECK (
            QBO_SyncStatus IN (N'NotSynced', N'Synced', N'Error', N'Pending')
        );
END;
GO

-- Migrate legacy single-line Address into BillAddrLine1 when empty.
UPDATE dbo.Supplier
SET BillAddrLine1 = Address
WHERE BillAddrLine1 IS NULL
  AND Address IS NOT NULL
  AND LTRIM(RTRIM(Address)) <> N'';
GO
