/*
  NutraAxis Operations — Practitioner wholesale tier thresholds, overrides, and run logs.
  Stage group IDs: Practitioner=4, Bronze=23, Silver=24, Gold=25, Platinum=26
  Prod group IDs:  Practitioner=4, Bronze=21, Silver=22, Gold=23, Platinum=24
*/

IF OBJECT_ID(N'dbo.PractitionerTierThreshold', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.PractitionerTierThreshold (
        TierCode        NVARCHAR(40)    NOT NULL,
        DisplayName     NVARCHAR(100)   NOT NULL,
        MinRevenue      DECIMAL(18,2)   NOT NULL,
        MaxRevenue      DECIMAL(18,2)   NULL,
        StageGroupId    INT             NOT NULL,
        ProdGroupId     INT             NOT NULL,
        SortOrder       INT             NOT NULL,
        IsActive        BIT             NOT NULL CONSTRAINT DF_PractitionerTierThreshold_IsActive DEFAULT (1),
        UpdatedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_PractitionerTierThreshold_UpdatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_PractitionerTierThreshold PRIMARY KEY CLUSTERED (TierCode),
        CONSTRAINT CK_PractitionerTierThreshold_MinMax CHECK (
            MaxRevenue IS NULL OR MaxRevenue >= MinRevenue
        )
    );
END
GO

MERGE dbo.PractitionerTierThreshold AS t
USING (VALUES
    (N'practitioner', N'Practitioner',           CAST(0 AS DECIMAL(18,2)),         CAST(100000.00 AS DECIMAL(18,2)), 4, 4, 10),
    (N'bronze',       N'Bronze-Practitioner',    CAST(100000.01 AS DECIMAL(18,2)), CAST(250000.00 AS DECIMAL(18,2)), 23, 21, 20),
    (N'silver',       N'Silver-Practitioner',    CAST(250000.01 AS DECIMAL(18,2)), CAST(350000.00 AS DECIMAL(18,2)), 24, 22, 30),
    (N'gold',         N'Gold-Practitioner',      CAST(350000.01 AS DECIMAL(18,2)), CAST(500000.00 AS DECIMAL(18,2)), 25, 23, 40),
    (N'platinum',     N'Platinum-Practitioner',  CAST(500000.01 AS DECIMAL(18,2)), NULL,                             26, 24, 50)
) AS s (TierCode, DisplayName, MinRevenue, MaxRevenue, StageGroupId, ProdGroupId, SortOrder)
ON t.TierCode = s.TierCode
WHEN MATCHED THEN
    UPDATE SET
        DisplayName  = s.DisplayName,
        MinRevenue   = s.MinRevenue,
        MaxRevenue   = s.MaxRevenue,
        StageGroupId = s.StageGroupId,
        ProdGroupId  = s.ProdGroupId,
        SortOrder    = s.SortOrder,
        IsActive     = 1,
        UpdatedAt    = SYSUTCDATETIME()
WHEN NOT MATCHED THEN
    INSERT (TierCode, DisplayName, MinRevenue, MaxRevenue, StageGroupId, ProdGroupId, SortOrder, IsActive)
    VALUES (s.TierCode, s.DisplayName, s.MinRevenue, s.MaxRevenue, s.StageGroupId, s.ProdGroupId, s.SortOrder, 1);
GO

IF OBJECT_ID(N'dbo.PractitionerTierOverride', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.PractitionerTierOverride (
        OverrideID          INT             NOT NULL IDENTITY(1,1),
        AccsCompanyId       INT             NOT NULL,
        SourceEnvironment   NVARCHAR(20)    NOT NULL,
        LockedGroupId       INT             NULL,
        LockedTierCode      NVARCHAR(40)    NULL,
        IsLocked            BIT             NOT NULL CONSTRAINT DF_PractitionerTierOverride_IsLocked DEFAULT (0),
        Notes               NVARCHAR(1000)  NULL,
        LockedBy            NVARCHAR(200)   NULL,
        LockedAt            DATETIME2(0)    NULL,
        UpdatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_PractitionerTierOverride_UpdatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_PractitionerTierOverride PRIMARY KEY CLUSTERED (OverrideID),
        CONSTRAINT UQ_PractitionerTierOverride_CompanyEnv UNIQUE (AccsCompanyId, SourceEnvironment),
        CONSTRAINT CK_PractitionerTierOverride_SourceEnvironment CHECK (
            SourceEnvironment IN (N'production', N'stage')
        )
    );

    CREATE NONCLUSTERED INDEX IX_PractitionerTierOverride_Env
        ON dbo.PractitionerTierOverride (SourceEnvironment, IsLocked);
END
GO

IF OBJECT_ID(N'dbo.PractitionerTierRunLog', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.PractitionerTierRunLog (
        RunLogID            INT             NOT NULL IDENTITY(1,1),
        SourceEnvironment   NVARCHAR(20)    NOT NULL,
        TriggerType         NVARCHAR(30)    NOT NULL,
        TriggeredBy         NVARCHAR(200)   NULL,
        StartedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_PractitionerTierRunLog_StartedAt DEFAULT (SYSUTCDATETIME()),
        FinishedAt          DATETIME2(0)    NULL,
        Status              NVARCHAR(20)    NOT NULL CONSTRAINT DF_PractitionerTierRunLog_Status DEFAULT (N'running'),
        CompaniesScanned    INT             NOT NULL CONSTRAINT DF_PractitionerTierRunLog_Scanned DEFAULT (0),
        CompaniesUpdated    INT             NOT NULL CONSTRAINT DF_PractitionerTierRunLog_Updated DEFAULT (0),
        CompaniesSkipped    INT             NOT NULL CONSTRAINT DF_PractitionerTierRunLog_Skipped DEFAULT (0),
        CompaniesFailed     INT             NOT NULL CONSTRAINT DF_PractitionerTierRunLog_Failed DEFAULT (0),
        Message             NVARCHAR(1000)  NULL,

        CONSTRAINT PK_PractitionerTierRunLog PRIMARY KEY CLUSTERED (RunLogID),
        CONSTRAINT CK_PractitionerTierRunLog_SourceEnvironment CHECK (
            SourceEnvironment IN (N'production', N'stage')
        ),
        CONSTRAINT CK_PractitionerTierRunLog_Status CHECK (
            Status IN (N'running', N'success', N'failed', N'partial')
        )
    );

    CREATE NONCLUSTERED INDEX IX_PractitionerTierRunLog_EnvStarted
        ON dbo.PractitionerTierRunLog (SourceEnvironment, StartedAt DESC);
END
GO

IF OBJECT_ID(N'dbo.PractitionerTierRunDetail', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.PractitionerTierRunDetail (
        RunDetailID         INT             NOT NULL IDENTITY(1,1),
        RunLogID            INT             NOT NULL,
        AccsCompanyId       INT             NOT NULL,
        CompanyName         NVARCHAR(255)   NULL,
        YtdRevenue          DECIMAL(18,2)   NULL,
        ProviderRevenue     DECIMAL(18,2)   NULL,
        PatientRevenue      DECIMAL(18,2)   NULL,
        FromGroupId         INT             NULL,
        ToGroupId           INT             NULL,
        SuggestedTierCode   NVARCHAR(40)    NULL,
        ActionStatus        NVARCHAR(30)    NOT NULL,
        SkipReason          NVARCHAR(100)   NULL,
        DetailMessage       NVARCHAR(1000)  NULL,
        CreatedAt           DATETIME2(0)    NOT NULL CONSTRAINT DF_PractitionerTierRunDetail_CreatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_PractitionerTierRunDetail PRIMARY KEY CLUSTERED (RunDetailID),
        CONSTRAINT FK_PractitionerTierRunDetail_RunLog
            FOREIGN KEY (RunLogID) REFERENCES dbo.PractitionerTierRunLog (RunLogID)
    );

    CREATE NONCLUSTERED INDEX IX_PractitionerTierRunDetail_Run
        ON dbo.PractitionerTierRunDetail (RunLogID, AccsCompanyId);
END
GO
