/*
  Inventory Balances weekly Excel report alert + seed subscribers
  (Tamika Thompson, Dina Scott).
*/

IF NOT EXISTS (SELECT 1 FROM dbo.AlertMessage WHERE AlertName = N'inventory-balances-weekly')
    INSERT INTO dbo.AlertMessage (AlertName, AlertStatus, AlertDescription)
    VALUES (
        N'inventory-balances-weekly',
        1,
        N'Weekly Inventory Balances Excel (SKU Master COGS) — Mondays AM Central.'
    );
GO

INSERT INTO dbo.AlertSubscription (alertID, UserID, AddressType)
SELECT am.alertID, u.UserID, N'TO'
FROM dbo.AlertMessage am
INNER JOIN dbo.[User] u ON u.UserID IN (7, 8)  -- Dina Scott, Tamika Thompson
WHERE am.AlertName = N'inventory-balances-weekly'
  AND am.AlertStatus = 1
  AND NOT EXISTS (
      SELECT 1
      FROM dbo.AlertSubscription existing
      WHERE existing.alertID = am.alertID
        AND existing.UserID = u.UserID
  );
GO
