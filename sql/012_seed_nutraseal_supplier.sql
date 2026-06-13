/*
  NutraAxis Operations — NutraSeal supplier from sample PO
*/

MERGE dbo.Supplier AS target
USING (
    SELECT
        N'NUTRASEAL' AS SupplierCode,
        N'NutraSeal, Inc' AS SupplierName,
        N'794 Sunrise Blvd., Mount Bethel, PA 18343' AS Address,
        N'Thomas Chapman' AS ContactName,
        N'chapmansan@hotmail.com' AS ContactEmail,
        N'310-413-1169' AS ContactPhone
) AS source
    ON target.SupplierCode = source.SupplierCode
WHEN MATCHED THEN
    UPDATE SET
        SupplierName = source.SupplierName,
        Address = source.Address,
        ContactName = source.ContactName,
        ContactEmail = source.ContactEmail,
        ContactPhone = source.ContactPhone,
        ModifiedDate = SYSUTCDATETIME()
WHEN NOT MATCHED BY TARGET THEN
    INSERT (SupplierCode, SupplierName, Address, ContactName, ContactEmail, ContactPhone, CreateDate, ModifiedDate)
    VALUES (source.SupplierCode, source.SupplierName, source.Address, source.ContactName, source.ContactEmail, source.ContactPhone, SYSUTCDATETIME(), SYSUTCDATETIME());
GO
