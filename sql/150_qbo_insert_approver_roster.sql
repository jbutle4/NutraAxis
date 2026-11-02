/*
  Narrow QBO Insert Approval to accounting, All Approver, Admin,
  and the dedicated QBO Insert Approver role.

  Management User keeps Payment Approval but does not approve QBO bill insert.
  Testing Approver keeps the permission for UAT routing only; production
  emails exclude that role in PHP.
*/

UPDATE dbo.Role
SET
    QBOInsertApproval = NULL,
    ModifiedbyUser = 1
WHERE RoleName = N'Management User';
GO

DELETE sub
FROM dbo.AlertSubscription sub
INNER JOIN dbo.AlertMessage am ON am.alertID = sub.alertID
INNER JOIN dbo.[User] u ON u.UserID = sub.UserID
INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
WHERE am.AlertName IN (
        N'qbo-insert-approval-request',
        N'qbo-insert-status-update',
        N'qbo-insert-viewed-by-approver'
    )
  AND (
      r.QBOInsertApproval IS NULL
      OR LTRIM(RTRIM(r.QBOInsertApproval)) = N''
      OR r.QBOInsertApproval NOT LIKE N'%U%'
      OR r.RoleName = N'Testing Approver'
  );
GO
