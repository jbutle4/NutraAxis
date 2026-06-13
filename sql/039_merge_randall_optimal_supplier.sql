/*
  NutraAxis Operations — merge duplicate Randall Optimal supplier records
  Keeps SUP-006 (full contact/address) and removes RANDALL-OPTIMAL seed duplicate.
*/

DECLARE @KeepSupplierID INT;
DECLARE @DropSupplierID INT;

SELECT @KeepSupplierID = SupplierID
FROM dbo.Supplier
WHERE SupplierCode = N'SUP-006'
   OR (SupplierName = N'Randall Optimal' AND SupplierCode IS NOT NULL AND SupplierCode <> N'RANDALL-OPTIMAL');

SELECT @DropSupplierID = SupplierID
FROM dbo.Supplier
WHERE SupplierCode = N'RANDALL-OPTIMAL';

IF @KeepSupplierID IS NULL
BEGIN
    SELECT TOP (1) @KeepSupplierID = SupplierID
    FROM dbo.Supplier
    WHERE SupplierName = N'Randall Optimal'
    ORDER BY SupplierID;
END;

IF @DropSupplierID IS NOT NULL
   AND @KeepSupplierID IS NOT NULL
   AND @DropSupplierID <> @KeepSupplierID
BEGIN
    UPDATE dbo.SKUMaster
    SET SupplierID = @KeepSupplierID
    WHERE SupplierID = @DropSupplierID;

    UPDATE dbo.PurchaseOrder
    SET SupplierID = @KeepSupplierID
    WHERE SupplierID = @DropSupplierID;

    DELETE FROM dbo.Supplier
    WHERE SupplierID = @DropSupplierID;
END;
GO
