-- Hardening: ai_budget alert rule + warn threshold (idempotent)

MERGE dbo.MktSetting AS target
USING (VALUES
    (N'alerts.budget_warn_pct', N'80', N'ai_budget warns when month-to-date AI spend reaches this percent of ai.monthly_budget_usd, or the month-end forecast passes the budget. Reaching the budget is a separate high alert.')
) AS source (SettingKey, SettingValue, Description)
ON target.SettingKey = source.SettingKey
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, Description) VALUES (source.SettingKey, source.SettingValue, source.Description);
GO

UPDATE dbo.MktSetting
SET SettingValue = SettingValue + NCHAR(10) + N'ai_budget',
    Description = N'Alert rules that are on, one per line: job_failed, traffic_drop, legacy_brand, site_error, escalation_overdue, ai_budget.'
WHERE SettingKey = N'alerts.rules'
  AND (NCHAR(10) + REPLACE(SettingValue, NCHAR(13), N'') + NCHAR(10)) NOT LIKE N'%' + NCHAR(10) + N'ai_budget' + NCHAR(10) + N'%';
GO
