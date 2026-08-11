/*
  NutraAxis Operations — Sales Team Territory Assignments
  Zip/county → sales rep map (from Sales Team Territory Map.xlsx).
*/

IF OBJECT_ID(N'dbo.SalesTeamTerritoryAssignments', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.SalesTeamTerritoryAssignments (
        SalesTeamTerritoryAssignmentID  INT             NOT NULL IDENTITY(1,1),
        State                           NVARCHAR(2)     NOT NULL,
        ZipCode                         NVARCHAR(10)    NOT NULL,
        County                          NVARCHAR(100)   NOT NULL,
        Rep                             NVARCHAR(200)   NOT NULL,
        PreviousRepAssigned             NVARCHAR(200)   NULL,
        DateAdded                       DATETIME2(0)    NOT NULL
            CONSTRAINT DF_SalesTeamTerritoryAssignments_DateAdded DEFAULT (SYSUTCDATETIME()),
        DateModified                    DATETIME2(0)    NOT NULL
            CONSTRAINT DF_SalesTeamTerritoryAssignments_DateModified DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_SalesTeamTerritoryAssignments
            PRIMARY KEY CLUSTERED (SalesTeamTerritoryAssignmentID)
    );

    CREATE UNIQUE NONCLUSTERED INDEX UX_SalesTeamTerritoryAssignments_StateZipCounty
        ON dbo.SalesTeamTerritoryAssignments (State, ZipCode, County);

    CREATE NONCLUSTERED INDEX IX_SalesTeamTerritoryAssignments_Rep
        ON dbo.SalesTeamTerritoryAssignments (Rep);

    CREATE NONCLUSTERED INDEX IX_SalesTeamTerritoryAssignments_ZipCode
        ON dbo.SalesTeamTerritoryAssignments (ZipCode);
END;
GO
