/*
  NutraAxis Operations — Weekly NA Status Update (deck updater)
*/

IF OBJECT_ID(N'dbo.status_week', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.status_week (
        id              INT             NOT NULL IDENTITY(1,1),
        status_date     DATE            NOT NULL,
        state           VARCHAR(12)     NOT NULL,
        base_blob       NVARCHAR(400)   NOT NULL,
        final_blob      NVARCHAR(400)   NULL,
        opened_by       NVARCHAR(120)   NOT NULL,
        opened_at       DATETIME2(0)    NOT NULL CONSTRAINT DF_status_week_opened DEFAULT (SYSUTCDATETIME()),
        published_by    NVARCHAR(120)   NULL,
        published_at    DATETIME2(0)    NULL,
        CONSTRAINT PK_status_week PRIMARY KEY CLUSTERED (id),
        CONSTRAINT UQ_status_week_date UNIQUE (status_date),
        CONSTRAINT CK_status_week_state CHECK (state IN (N'draft', N'published'))
    );
END;
GO

IF OBJECT_ID(N'dbo.status_template', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.status_template (
        id                  INT             NOT NULL IDENTITY(1,1),
        blob_path           NVARCHAR(400)   NOT NULL,
        archived_blob_path  NVARCHAR(400)   NULL,
        use_for_next_week   BIT             NOT NULL CONSTRAINT DF_status_tpl_next DEFAULT (0),
        field_count         INT             NOT NULL,
        uploaded_by         NVARCHAR(120)   NOT NULL,
        uploaded_at         DATETIME2(0)    NOT NULL CONSTRAINT DF_status_tpl_uploaded DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT PK_status_template PRIMARY KEY CLUSTERED (id)
    );
END;
GO

IF OBJECT_ID(N'dbo.status_change', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.status_change (
        id          BIGINT          NOT NULL IDENTITY(1,1),
        week_id     INT             NOT NULL,
        field_key   NVARCHAR(120)   NOT NULL,
        old_value   NVARCHAR(MAX)   NOT NULL,
        new_value   NVARCHAR(MAX)   NOT NULL,
        source      VARCHAR(8)      NOT NULL,
        reason      NVARCHAR(400)   NULL,
        author      NVARCHAR(120)   NOT NULL,
        created_at  DATETIME2(0)    NOT NULL CONSTRAINT DF_status_change_created DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT PK_status_change PRIMARY KEY CLUSTERED (id),
        CONSTRAINT FK_status_change_week FOREIGN KEY (week_id) REFERENCES dbo.status_week (id),
        CONSTRAINT CK_status_change_source CHECK (source IN (N'ai', N'manual', N'revert'))
    );

    CREATE NONCLUSTERED INDEX IX_status_change_week_field
        ON dbo.status_change (week_id, field_key, id DESC);
END;
GO

IF OBJECT_ID(N'dbo.status_chat_message', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.status_chat_message (
        id          BIGINT          NOT NULL IDENTITY(1,1),
        week_id     INT             NOT NULL,
        author      NVARCHAR(120)   NOT NULL,
        role        VARCHAR(10)     NOT NULL,
        content     NVARCHAR(MAX)   NOT NULL,
        created_at  DATETIME2(0)    NOT NULL CONSTRAINT DF_status_chat_created DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT PK_status_chat_message PRIMARY KEY CLUSTERED (id),
        CONSTRAINT FK_status_chat_week FOREIGN KEY (week_id) REFERENCES dbo.status_week (id),
        CONSTRAINT CK_status_chat_role CHECK (role IN (N'user', N'assistant'))
    );

    CREATE NONCLUSTERED INDEX IX_status_chat_week_author
        ON dbo.status_chat_message (week_id, author, id);
END;
GO

IF COL_LENGTH(N'dbo.Role', N'StatusUpdate') IS NULL
    ALTER TABLE dbo.Role ADD StatusUpdate NVARCHAR(10) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Role_StatusUpdate_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
        ADD CONSTRAINT CK_Role_StatusUpdate_CRUD
        CHECK (StatusUpdate IS NULL OR StatusUpdate IN (
            N'C', N'R', N'U', N'D',
            N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
            N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
        ));
GO

IF COL_LENGTH(N'dbo.Role', N'StatusUpdateTemplate') IS NULL
    ALTER TABLE dbo.Role ADD StatusUpdateTemplate NVARCHAR(10) NULL;
GO

IF OBJECT_ID(N'dbo.CK_Role_StatusUpdateTemplate_CRUD', N'C') IS NULL
    ALTER TABLE dbo.Role
        ADD CONSTRAINT CK_Role_StatusUpdateTemplate_CRUD
        CHECK (StatusUpdateTemplate IS NULL OR StatusUpdateTemplate IN (
            N'C', N'R', N'U', N'D',
            N'CR', N'CU', N'CD', N'RU', N'RD', N'UD',
            N'CRU', N'CRD', N'CUD', N'RUD', N'CRUD'
        ));
GO

UPDATE dbo.Role SET StatusUpdate = N'CRUD', StatusUpdateTemplate = N'CRUD' WHERE RoleName = N'Admin';
GO