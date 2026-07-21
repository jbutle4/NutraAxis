/*
  NutraAxis Operations — allow one QBOConnection row per Environment (sandbox + production).
*/

/* Keep the newest row per environment if duplicates exist. */
;WITH ranked AS (
    SELECT
        ConnectionID,
        ROW_NUMBER() OVER (
            PARTITION BY Environment
            ORDER BY UpdatedAt DESC, ConnectionID DESC
        ) AS rn
    FROM dbo.QBOConnection
)
DELETE FROM ranked WHERE rn > 1;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = N'UX_QBOConnection_Environment'
      AND object_id = OBJECT_ID(N'dbo.QBOConnection')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_QBOConnection_Environment
        ON dbo.QBOConnection (Environment);
END
GO
