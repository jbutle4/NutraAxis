/*
  NutraAxis Operations — Randall Optimal CMO supplier
*/

MERGE dbo.Supplier AS target
USING (
    SELECT
        N'SUP-006' AS SupplierCode,
        N'Randall Optimal' AS SupplierName
) AS source
    ON target.SupplierName = source.SupplierName
WHEN MATCHED THEN
    UPDATE SET
        SupplierCode = COALESCE(target.SupplierCode, source.SupplierCode),
        ModifiedDate = SYSUTCDATETIME()
WHEN NOT MATCHED BY TARGET THEN
    INSERT (SupplierCode, SupplierName, CreateDate, ModifiedDate)
    VALUES (source.SupplierCode, source.SupplierName, SYSUTCDATETIME(), SYSUTCDATETIME());
GO
