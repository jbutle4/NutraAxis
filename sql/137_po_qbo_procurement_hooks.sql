/*
  NutraAxis Operations — PO QuickBooks sync metadata for approval hook
*/

IF COL_LENGTH('dbo.PurchaseOrder', 'POQBO_LastSyncError') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD POQBO_LastSyncError NVARCHAR(1000) NULL;

IF COL_LENGTH('dbo.PurchaseOrder', 'POQBO_LastSyncAt') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD POQBO_LastSyncAt DATETIME2(0) NULL;
