/*
  NutraAxis Operations — CMO off-site storage (company-owned inventory at CMO for rework).
*/

IF OBJECT_ID(N'dbo.CK_Facility_FacilityType', N'C') IS NOT NULL
    ALTER TABLE dbo.Facility DROP CONSTRAINT CK_Facility_FacilityType;
GO

ALTER TABLE dbo.Facility
    ADD CONSTRAINT CK_Facility_FacilityType CHECK (
        FacilityType IN (
            N'Warehouse', N'3PL', N'CPPC', N'Transit',
            N'Virtual', N'QC Hold', N'Other', N'CMO'
        )
    );
GO

DECLARE @SeedUserID INT;
SELECT @SeedUserID = MIN(UserID) FROM dbo.[User];

IF @SeedUserID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM dbo.Facility WHERE FacilityCode = N'CMO')
BEGIN
    INSERT INTO dbo.Facility (
        FacilityCode, FacilityName, FacilityType, IsActive, Notes, CreatedByUser
    )
    VALUES (
        N'CMO',
        N'CMO — Off-site rework storage',
        N'CMO',
        1,
        N'Company-owned inventory physically at a contract manufacturer for rework. Not a PO receipt destination — receive rework returns at CART via PO Receiving, then transfer CMO → CART.',
        @SeedUserID
    );
END;
GO

IF COL_LENGTH('dbo.Facility', 'IsMothership') IS NOT NULL
BEGIN
    UPDATE dbo.Facility
    SET
        IsMothership = 0,
        ReceivesPurchaseOrders = 0,
        IntegrationMode = N'Local'
    WHERE FacilityCode = N'CMO';
END;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.InvReasonCode WHERE ReasonCode = N'CMO_REWORK')
    INSERT INTO dbo.InvReasonCode (ReasonCode, Description, AppliesToTransfer)
    VALUES (N'CMO_REWORK', N'Transfer to/from CMO for rework (company-owned stock)', 1);
GO

IF COL_LENGTH('dbo.InvTransfer', 'SupplierID') IS NULL
    ALTER TABLE dbo.InvTransfer ADD SupplierID INT NULL;
GO

IF COL_LENGTH('dbo.InvTransfer', 'ReworkReturnPOID') IS NULL
    ALTER TABLE dbo.InvTransfer ADD ReworkReturnPOID INT NULL;
GO

IF COL_LENGTH('dbo.InvTransfer', 'SupplierID') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.foreign_keys
       WHERE name = N'FK_InvTransfer_Supplier'
   )
    ALTER TABLE dbo.InvTransfer
        ADD CONSTRAINT FK_InvTransfer_Supplier FOREIGN KEY (SupplierID)
            REFERENCES dbo.Supplier (SupplierID);
GO

IF COL_LENGTH('dbo.InvTransfer', 'ReworkReturnPOID') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.foreign_keys
       WHERE name = N'FK_InvTransfer_ReworkReturnPO'
   )
    ALTER TABLE dbo.InvTransfer
        ADD CONSTRAINT FK_InvTransfer_ReworkReturnPO FOREIGN KEY (ReworkReturnPOID)
            REFERENCES dbo.PurchaseOrder (POID);
GO

IF COL_LENGTH('dbo.PurchaseOrder', 'SourceTransferID') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD SourceTransferID INT NULL;
GO

IF COL_LENGTH('dbo.PurchaseOrder', 'SourceTransferID') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.foreign_keys
       WHERE name = N'FK_PurchaseOrder_SourceTransfer'
   )
    ALTER TABLE dbo.PurchaseOrder
        ADD CONSTRAINT FK_PurchaseOrder_SourceTransfer FOREIGN KEY (SourceTransferID)
            REFERENCES dbo.InvTransfer (TransferID);
GO
