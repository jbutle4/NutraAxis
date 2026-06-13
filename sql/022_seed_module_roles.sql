/*
  NutraAxis Operations — dedicated roles for Legal, Product Catalog, and Links Index modules
*/

MERGE dbo.Role AS target
USING (
    VALUES
        (
            N'Legal User',
            N'Manage legal agreements and contracts in Operations.',
            N'CRUD'
        ),
        (
            N'Catalog User',
            N'Maintain the product catalog and SKU master in Operations.',
            N'CRUD'
        ),
        (
            N'Links User',
            N'Manage the links index and team resource bookmarks in Operations.',
            N'CRUD'
        )
) AS source (RoleName, RoleDesc, ModulePermission)
    ON target.RoleName = source.RoleName
WHEN MATCHED THEN
    UPDATE SET
        RoleDesc = source.RoleDesc,
        LegalAgreements = CASE WHEN source.RoleName = N'Legal User' THEN source.ModulePermission ELSE target.LegalAgreements END,
        ProductCatalog = CASE WHEN source.RoleName = N'Catalog User' THEN source.ModulePermission ELSE target.ProductCatalog END,
        LinksIndex = CASE WHEN source.RoleName = N'Links User' THEN source.ModulePermission ELSE target.LinksIndex END,
        OperationsDashboard = N'R',
        ModifiedbyUser = 1
WHEN NOT MATCHED BY TARGET THEN
    INSERT (
        RoleName,
        RoleDesc,
        RoleCreateDate,
        ModifiedbyUser,
        LegalAgreements,
        ProductCatalog,
        LinksIndex,
        OperationsDashboard
    )
    VALUES (
        source.RoleName,
        source.RoleDesc,
        SYSUTCDATETIME(),
        1,
        CASE WHEN source.RoleName = N'Legal User' THEN source.ModulePermission END,
        CASE WHEN source.RoleName = N'Catalog User' THEN source.ModulePermission END,
        CASE WHEN source.RoleName = N'Links User' THEN source.ModulePermission END,
        N'R'
    );
GO
