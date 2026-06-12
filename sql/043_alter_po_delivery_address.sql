/*
  NutraAxis Operations — purchase order delivery address
*/

IF COL_LENGTH('dbo.PurchaseOrder', 'DeliveryAddress') IS NULL
    ALTER TABLE dbo.PurchaseOrder ADD DeliveryAddress NVARCHAR(500) NULL;
GO
