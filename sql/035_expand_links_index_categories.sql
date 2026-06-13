/*
  NutraAxis Operations — expand Links Index categories for bookmark import
*/

IF OBJECT_ID(N'dbo.CK_LinksIndex_Category', N'C') IS NOT NULL
    ALTER TABLE dbo.LinksIndex DROP CONSTRAINT CK_LinksIndex_Category;
GO

ALTER TABLE dbo.LinksIndex
ADD CONSTRAINT CK_LinksIndex_Category CHECK (
    LinkCategory IN (
        N'Web application',
        N'MS365 Application',
        N'Document',
        N'External Website - Reference',
        N'Other',
        N'Accounting',
        N'eCommerce',
        N'IT',
        N'Marketing',
        N'Marketing-IT',
        N'NA Operational',
        N'Reference',
        N'Support'
    )
);
GO
