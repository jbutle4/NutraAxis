/*
  NutraAxis Operations — Restore ApprovalLog.LogDate default

  Migration 088 renamed POApprovalLog to ApprovalLog; the LogDate default
  constraint was not carried forward. approval_append_log now sets LogDate
  explicitly; this restores the column default as a safety net.
*/

IF COL_LENGTH('dbo.ApprovalLog', 'LogDate') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.default_constraints dc
       INNER JOIN sys.columns c
           ON c.object_id = dc.parent_object_id
          AND c.column_id = dc.parent_column_id
       WHERE dc.parent_object_id = OBJECT_ID(N'dbo.ApprovalLog')
         AND c.name = N'LogDate'
   )
BEGIN
    ALTER TABLE dbo.ApprovalLog
        ADD CONSTRAINT DF_ApprovalLog_LogDate DEFAULT (SYSUTCDATETIME()) FOR LogDate;
END
GO
