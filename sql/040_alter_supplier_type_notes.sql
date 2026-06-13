/*
  NutraAxis Operations — supplier type and notes
*/

IF COL_LENGTH('dbo.Supplier', 'SupplierType') IS NULL
    ALTER TABLE dbo.Supplier ADD SupplierType NVARCHAR(50) NULL;

IF COL_LENGTH('dbo.Supplier', 'Notes') IS NULL
    ALTER TABLE dbo.Supplier ADD Notes NVARCHAR(MAX) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Supplier_SupplierType', N'C') IS NULL
BEGIN
    ALTER TABLE dbo.Supplier
        ADD CONSTRAINT CK_Supplier_SupplierType CHECK (
            SupplierType IS NULL OR SupplierType IN (
                N'CMO',
                N'Marketing',
                N'IT Supplier',
                N'Independent Contractor',
                N'IT Contractor',
                N'Education Contractor',
                N'Legal/Rgulatory Contractor',
                N'Labor Contactor',
                N'Other Contractor',
                N'Other Supplier'
            )
        );
END;
GO

UPDATE dbo.Supplier
SET SupplierType = N'CMO'
WHERE SupplierType IS NULL
  AND (
      SupplierCode IN (N'NUTRASEAL', N'SUP-006', N'SUP-007')
      OR SupplierName IN (N'NutraSeal, Inc', N'Randall Optimal', N'IFF-HealthWright', N'Vitaquest')
  );
GO
