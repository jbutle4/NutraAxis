-- Correct Marketing setting descriptions that did not match behaviour (idempotent)

UPDATE target
SET Description = source.Description
FROM dbo.MktSetting AS target
INNER JOIN (VALUES
    (N'ai.default_provider', N'Not used: each prompt sets its own provider in Prompt Lab. Changing this has no effect.'),
    (N'brand.terms', N'Not used: no job or page reads this list. To catch old brand names on the website, use brand.legacy_terms.'),
    (N'claims.disclaimer', N'DSHEA disclaimer given to the AI to write after claims marked ‡ in assets and drafts. The portal does not add it itself; the claims check flags it when missing.'),
    (N'harvest.max_items_per_run', N'Most items the harvester takes from one source in one run (feed entries, search results or crawled pages). Higher means more items to score and more AI spend.'),
    (N'semrush.monthly_unit_budget', N'Not used yet: no scheduled job calls Semrush. Changing this has no effect for now.')
) AS source (SettingKey, Description)
    ON source.SettingKey = target.SettingKey
WHERE ISNULL(target.Description, N'') <> source.Description;
GO
