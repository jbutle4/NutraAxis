/*
  NutraAxis Operations — widen POStatus for longer approval workflow labels
  e.g. "Submitted for Approval" (22), "Submitted to Accounting for Payment" (35)
*/

ALTER TABLE dbo.PurchaseOrder ALTER COLUMN POStatus NVARCHAR(50) NOT NULL;
GO
