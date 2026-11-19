/*
  Pharmacy IMS facilities — OCALA, Dallas, Melbourne (dispensing locations).
*/

DECLARE @SeedUserID INT;
SELECT @SeedUserID = MIN(UserID) FROM dbo.[User];

IF @SeedUserID IS NOT NULL
BEGIN

IF NOT EXISTS (SELECT 1 FROM dbo.Facility WHERE FacilityCode = N'OCALA')
BEGIN
    INSERT INTO dbo.Facility (
        FacilityCode, FacilityName, FacilityType, IsActive, Notes, CreatedByUser
    )
    VALUES (
        N'OCALA',
        N'Ocala Pharmacy',
        N'CPPC',
        1,
        N'Pharmacy dispensing location — Ocala, FL.',
        @SeedUserID
    );
END;

IF NOT EXISTS (SELECT 1 FROM dbo.Facility WHERE FacilityCode = N'DALLAS')
BEGIN
    INSERT INTO dbo.Facility (
        FacilityCode, FacilityName, FacilityType, IsActive, Notes, CreatedByUser
    )
    VALUES (
        N'DALLAS',
        N'Dallas Pharmacy',
        N'CPPC',
        1,
        N'Pharmacy dispensing location — Dallas.',
        @SeedUserID
    );
END;

IF NOT EXISTS (SELECT 1 FROM dbo.Facility WHERE FacilityCode = N'MELBOURNE')
BEGIN
    INSERT INTO dbo.Facility (
        FacilityCode, FacilityName, FacilityType, IsActive, Notes, CreatedByUser
    )
    VALUES (
        N'MELBOURNE',
        N'Melbourne Pharmacy',
        N'CPPC',
        1,
        N'Pharmacy dispensing location — Melbourne, FL.',
        @SeedUserID
    );
END;

IF COL_LENGTH('dbo.Facility', 'IsMothership') IS NOT NULL
BEGIN
    UPDATE dbo.Facility
    SET
        IsMothership = 0,
        ReceivesPurchaseOrders = 0,
        IntegrationMode = N'Local'
    WHERE FacilityCode IN (N'OCALA', N'DALLAS', N'MELBOURNE');
END;

END;
GO