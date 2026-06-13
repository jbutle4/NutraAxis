/*
  NutraAxis Operations — add Transportation supplier type
*/

IF OBJECT_ID(N'dbo.CK_Supplier_SupplierType', N'C') IS NOT NULL
    ALTER TABLE dbo.Supplier DROP CONSTRAINT CK_Supplier_SupplierType;
GO

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
            N'Other Supplier',
            N'Transportation'
        )
    );
GO
