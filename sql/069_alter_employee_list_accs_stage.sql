/*
  NutraAxis Operations — track ACCS Stage customer provisioning per employee row
*/

IF COL_LENGTH(N'dbo.EmployeeList', N'AccsStageCustomerId') IS NULL
BEGIN
    ALTER TABLE dbo.EmployeeList
        ADD AccsStageCustomerId INT NULL;
END;
GO

IF COL_LENGTH(N'dbo.EmployeeList', N'AccsStageCustomerCreatedAt') IS NULL
BEGIN
    ALTER TABLE dbo.EmployeeList
        ADD AccsStageCustomerCreatedAt DATETIME2(0) NULL;
END;
GO

IF COL_LENGTH(N'dbo.EmployeeList', N'AccsStageCustomerLastError') IS NULL
BEGIN
    ALTER TABLE dbo.EmployeeList
        ADD AccsStageCustomerLastError NVARCHAR(500) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = N'IX_EmployeeList_FirstEmail_AccsStage'
      AND object_id = OBJECT_ID(N'dbo.EmployeeList')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_EmployeeList_FirstEmail_AccsStage
        ON dbo.EmployeeList (FirstEmail, AccsStageCustomerId)
        INCLUDE (Email, Group1, FirstName, LastName);
END;
GO
