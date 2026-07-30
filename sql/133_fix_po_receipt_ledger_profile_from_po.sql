/*
  NutraAxis Operations — align POReceipt.LedgerProfile with parent PurchaseOrder.

  sql/128 backfilled all existing receipts to uat while sql/130 classified
  non -UAT PO numbers as production, leaving production POs with uat receipts
  invisible on PO Management view.
*/

UPDATE r
SET r.LedgerProfile = po.LedgerProfile
FROM dbo.POReceipt r
INNER JOIN dbo.PurchaseOrder po ON po.POID = r.POID
WHERE r.LedgerProfile <> po.LedgerProfile;
GO
