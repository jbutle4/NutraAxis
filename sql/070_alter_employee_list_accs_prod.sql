/*
  NutraAxis Operations — track ACCS Production customer provisioning per employee row
*/

IF COL_LENGTH(N'dbo.EmployeeList', N'AccsProdCustomerId') IS NULL
BEGIN
    ALTER TABLE dbo.EmployeeList
        ADD AccsProdCustomerId INT NULL;
END;
GO

IF COL_LENGTH(N'dbo.EmployeeList', N'AccsProdCustomerCreatedAt') IS NULL
BEGIN
    ALTER TABLE dbo.EmployeeList
        ADD AccsProdCustomerCreatedAt DATETIME2(0) NULL;
END;
GO

IF COL_LENGTH(N'dbo.EmployeeList', N'AccsProdCustomerLastError') IS NULL
BEGIN
    ALTER TABLE dbo.EmployeeList
        ADD AccsProdCustomerLastError NVARCHAR(500) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = N'IX_EmployeeList_FirstEmail_AccsProd'
      AND object_id = OBJECT_ID(N'dbo.EmployeeList')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_EmployeeList_FirstEmail_AccsProd
        ON dbo.EmployeeList (FirstEmail, AccsProdCustomerId)
        INCLUDE (Email, Group1, FirstName, LastName);
END;
GO
