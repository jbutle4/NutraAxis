/*
  NutraAxis Operations — Marketing Publishing Calendar (SEO Ops S1b)

  Adds publishing fields to dbo.MktAsset:
    ScheduledBy          — who put the asset on the calendar
    LoadedAt / LoadedBy  — when the coordinator loaded it into GoHighLevel (ExternalPostID = GHL post or email campaign ID)
    PostedBy             — who marked it posted

  Seeds settings calendar.default_times and calendar.min_gap_hours.

  Reverse:
    ALTER TABLE dbo.MktAsset DROP COLUMN ScheduledBy, LoadedAt, LoadedBy, PostedBy;
    DELETE FROM dbo.MktSetting WHERE SettingKey LIKE N'calendar.%';
*/

IF COL_LENGTH(N'dbo.MktAsset', N'ScheduledBy') IS NULL
    ALTER TABLE dbo.MktAsset ADD
        ScheduledBy  INT           NULL,
        LoadedAt     DATETIME2(0)  NULL,
        LoadedBy     INT           NULL,
        PostedBy     INT           NULL;
GO

MERGE dbo.MktSetting AS target
USING (VALUES
    (N'calendar.default_times', N'linkedin|08:30
facebook|12:00
instagram|12:00
x|09:00
email|07:00',
     N'Default posting time per channel (Central time, 24-hour HH:MM), one channel|time per line. Used when a whole campaign is laid out on the calendar.'),
    (N'calendar.min_gap_hours', N'20',
     N'The calendar warns when two assets on the same channel are scheduled closer together than this many hours.')
) AS source (SettingKey, SettingValue, Description)
ON target.SettingKey = source.SettingKey
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, Description) VALUES (source.SettingKey, source.SettingValue, source.Description);
GO
