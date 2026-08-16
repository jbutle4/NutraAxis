/*
  Per-realm QuickBooks Item Ids on SKUMaster (sandbox vs production).
  Legacy QBO_ItemID / QBO_SyncToken are backfilled into sandbox columns.
*/

IF COL_LENGTH('dbo.SKUMaster', 'QBO_ItemID_Sandbox') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_ItemID_Sandbox NVARCHAR(32) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'QBO_ItemID_Production') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_ItemID_Production NVARCHAR(32) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'QBO_SyncToken_Sandbox') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_SyncToken_Sandbox NVARCHAR(32) NULL;

IF COL_LENGTH('dbo.SKUMaster', 'QBO_SyncToken_Production') IS NULL
    ALTER TABLE dbo.SKUMaster ADD QBO_SyncToken_Production NVARCHAR(32) NULL;
GO

UPDATE dbo.SKUMaster
SET QBO_ItemID_Sandbox = QBO_ItemID
WHERE QBO_ItemID IS NOT NULL
  AND LTRIM(RTRIM(QBO_ItemID)) <> N''
  AND (QBO_ItemID_Sandbox IS NULL OR LTRIM(RTRIM(QBO_ItemID_Sandbox)) = N'');

UPDATE dbo.SKUMaster
SET QBO_SyncToken_Sandbox = QBO_SyncToken
WHERE QBO_SyncToken IS NOT NULL
  AND LTRIM(RTRIM(QBO_SyncToken)) <> N''
  AND (QBO_SyncToken_Sandbox IS NULL OR LTRIM(RTRIM(QBO_SyncToken_Sandbox)) = N'');
GO
