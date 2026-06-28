/*
  NutraAxis Operations — IT Product "Other" choice label
*/

UPDATE dbo.EnhancementLog
SET ITProduct = N'Other - add in description'
WHERE ITProduct = N'Other';
GO

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = N'CK_EnhancementLog_ITProduct'
      AND parent_object_id = OBJECT_ID(N'dbo.EnhancementLog')
)
    ALTER TABLE dbo.EnhancementLog DROP CONSTRAINT CK_EnhancementLog_ITProduct;
GO

ALTER TABLE dbo.EnhancementLog
    ADD CONSTRAINT CK_EnhancementLog_ITProduct CHECK (
        ITProduct IS NULL
        OR ITProduct IN (
            N'ACCS',
            N'QBO',
            N'Operations Portal',
            N'Integration or Automation',
            N'Other - add in description'
        )
    );
GO
