/*
  NutraAxis Operations — rename CoaDocument.IsPublished to Publish
*/

IF COL_LENGTH('dbo.CoaDocument', 'Publish') IS NULL
   AND COL_LENGTH('dbo.CoaDocument', 'IsPublished') IS NOT NULL
BEGIN
    EXEC sp_rename 'dbo.CoaDocument.IsPublished', 'Publish', 'COLUMN';
END
GO
