/*
  NutraAxis Operations — CRUD permission value constraints on dbo.Role

  Each permission column stores a subset of CRUD letters in canonical order:
  C = Create, R = Read, U = Update, D = Delete
  NULL = no access

  Valid values: C, R, U, D, CR, CU, CD, RU, RD, UD, CRU, CRD, CUD, RUD, CRUD
*/

DECLARE @cols TABLE (ColName sysname);
INSERT INTO @cols (ColName) VALUES
    (N'POManagement'),
    (N'InventoryReporting'),
    (N'SalesReporting'),
    (N'InventoryForecasting'),
    (N'LabelingOperations'),
    (N'OperationsDashboard'),
    (N'UserAdmin'),
    (N'RoleAdmin');

DECLARE @col sysname;
DECLARE @constraint sysname;
DECLARE @sql nvarchar(max);

DECLARE col_cursor CURSOR LOCAL FAST_FORWARD FOR
    SELECT ColName FROM @cols;

OPEN col_cursor;
FETCH NEXT FROM col_cursor INTO @col;

WHILE @@FETCH_STATUS = 0
BEGIN
    SET @constraint = N'CK_Role_' + @col + N'_CRUD';

    IF NOT EXISTS (
        SELECT 1 FROM sys.check_constraints
        WHERE name = @constraint
          AND parent_object_id = OBJECT_ID(N'dbo.Role')
    )
    BEGIN
        SET @sql = N'
            ALTER TABLE dbo.Role
            ADD CONSTRAINT ' + QUOTENAME(@constraint) + N'
            CHECK (' + QUOTENAME(@col) + N' IS NULL OR ' + QUOTENAME(@col) + N' IN (
                N''C'', N''R'', N''U'', N''D'',
                N''CR'', N''CU'', N''CD'', N''RU'', N''RD'', N''UD'',
                N''CRU'', N''CRD'', N''CUD'', N''RUD'', N''CRUD''
            ));';

        EXEC sp_executesql @sql;
    END;

    FETCH NEXT FROM col_cursor INTO @col;
END;

CLOSE col_cursor;
DEALLOCATE col_cursor;
GO
