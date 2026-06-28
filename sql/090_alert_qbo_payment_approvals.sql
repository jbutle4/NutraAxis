/*
  Alert messages and default subscriptions for QBO insert and payment approval workflows.
*/

IF NOT EXISTS (SELECT 1 FROM dbo.AlertMessage WHERE AlertName = N'qbo-insert-approval-request')
    INSERT INTO dbo.AlertMessage (AlertName, AlertStatus, AlertDescription)
    VALUES (N'qbo-insert-approval-request', 1, N'Supplier invoice submitted or resubmitted for QBO insert approval (watchers).');
GO

IF NOT EXISTS (SELECT 1 FROM dbo.AlertMessage WHERE AlertName = N'qbo-insert-status-update')
    INSERT INTO dbo.AlertMessage (AlertName, AlertStatus, AlertDescription)
    VALUES (N'qbo-insert-status-update', 1, N'Supplier invoice approval status changed (approved, rejected, sent back, posted, etc.).');
GO

IF NOT EXISTS (SELECT 1 FROM dbo.AlertMessage WHERE AlertName = N'qbo-insert-viewed-by-approver')
    INSERT INTO dbo.AlertMessage (AlertName, AlertStatus, AlertDescription)
    VALUES (N'qbo-insert-viewed-by-approver', 1, N'QBO insert approver opened a submitted supplier invoice for review.');
GO

IF NOT EXISTS (SELECT 1 FROM dbo.AlertMessage WHERE AlertName = N'payment-approval-request')
    INSERT INTO dbo.AlertMessage (AlertName, AlertStatus, AlertDescription)
    VALUES (N'payment-approval-request', 1, N'Invoice payment submitted or resubmitted for approval (watchers).');
GO

IF NOT EXISTS (SELECT 1 FROM dbo.AlertMessage WHERE AlertName = N'payment-status-update')
    INSERT INTO dbo.AlertMessage (AlertName, AlertStatus, AlertDescription)
    VALUES (N'payment-status-update', 1, N'Invoice payment approval status changed.');
GO

IF NOT EXISTS (SELECT 1 FROM dbo.AlertMessage WHERE AlertName = N'payment-viewed-by-approver')
    INSERT INTO dbo.AlertMessage (AlertName, AlertStatus, AlertDescription)
    VALUES (N'payment-viewed-by-approver', 1, N'Payment approver opened a submitted payment for review.');
GO

INSERT INTO dbo.AlertSubscription (alertID, UserID)
SELECT am.alertID, u.UserID
FROM dbo.AlertMessage am
INNER JOIN dbo.[User] u ON 1 = 1
INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
WHERE am.AlertName = N'qbo-insert-approval-request'
  AND am.AlertStatus = 1
  AND r.QBOInsertApproval LIKE N'%R%'
  AND NOT EXISTS (
      SELECT 1
      FROM dbo.AlertSubscription existing
      WHERE existing.alertID = am.alertID
        AND existing.UserID = u.UserID
  );
GO

INSERT INTO dbo.AlertSubscription (alertID, UserID)
SELECT am.alertID, u.UserID
FROM dbo.AlertMessage am
INNER JOIN dbo.[User] u ON 1 = 1
INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
WHERE am.AlertName = N'payment-approval-request'
  AND am.AlertStatus = 1
  AND r.PaymentApproval LIKE N'%R%'
  AND NOT EXISTS (
      SELECT 1
      FROM dbo.AlertSubscription existing
      WHERE existing.alertID = am.alertID
        AND existing.UserID = u.UserID
  );
GO
