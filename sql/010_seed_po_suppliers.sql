/*
  NutraAxis Operations — seed sample suppliers for PO Management
*/

MERGE dbo.Supplier AS target
USING (
    VALUES
        (N'SUP-001', N'NutraBlend Ingredients', N'Laura Chen', N'orders@nutrablend.com', N'555-0101'),
        (N'SUP-002', N'Pacific Bottle & Label', N'Mark Rivera', N'po@pacificbottle.com', N'555-0102'),
        (N'SUP-003', N'Wellness Packaging Co.', N'Susan Hale', N'purchasing@wellnesspkg.com', N'555-0103'),
        (N'SUP-004', N'VitaSource Raw Materials', N'James Ortiz', N'supply@vitasource.com', N'555-0104')
) AS source (SupplierCode, SupplierName, ContactName, ContactEmail, ContactPhone)
    ON target.SupplierCode = source.SupplierCode
WHEN NOT MATCHED BY TARGET THEN
    INSERT (SupplierCode, SupplierName, ContactName, ContactEmail, ContactPhone, CreateDate, ModifiedDate)
    VALUES (source.SupplierCode, source.SupplierName, source.ContactName, source.ContactEmail, source.ContactPhone, SYSUTCDATETIME(), SYSUTCDATETIME());
GO
