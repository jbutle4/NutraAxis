/*
  All Approver can review supplier invoices in Accounting (read-only).
  QBO Insert Approval / Payment Approval alone does not open view.php.
*/

UPDATE dbo.Role
SET
    Accounting = N'R',
    ModifiedbyUser = 1
WHERE RoleName = N'All Approver'
  AND (Accounting IS NULL OR LTRIM(RTRIM(Accounting)) = N'');
GO
