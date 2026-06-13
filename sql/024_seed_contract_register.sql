/*
  NutraAxis Operations — seed ContractRegister from legal agreements inventory
*/

MERGE dbo.ContractRegister AS target
USING (
    VALUES
        (N'CTR-2026-001', N'NutraSeal Contract Manufacturing Agreement', N'NutraSeal Packaging', N'Manufacturing Agreement', N'Executed', NULL, CAST(N'2026-04-08' AS DATE), NULL, 0, NULL, NULL, N'NutraSeal Packaging'),
        (N'CTR-2026-002', N'NutraSeal Quality Agreement v2026-04', N'NutraSeal Packaging', N'Quality Agreement', N'Executed', NULL, NULL, N'Annual renewal', 1, 90, 0, N'NutraSeal Packaging'),
        (N'CTR-2026-003', N'VitaQuest Supply Agreement 2025', N'VitaQuest', N'Supply Agreement', N'Executed', NULL, CAST(N'2026-11-22' AS DATE), NULL, 0, NULL, NULL, N'VitaQuest'),
        (N'CTR-2026-004', N'VitaQuest Quality Agreement 2025', N'VitaQuest', N'Quality Agreement', N'Executed', NULL, NULL, N'Annual renewal', 1, 90, 0, N'VitaQuest'),
        (N'CTR-2026-005', N'IFF-HealthWright Manufacturing & Supply Agreement', N'IFF / HealthWright Products', N'Manufacturing Agreement', N'Executed', NULL, CAST(N'2026-03-07' AS DATE), NULL, 0, NULL, NULL, N'IFF / HealthWright Products'),
        (N'CTR-2026-006', N'IFF-HealthWright QA Agreement', N'IFF / HealthWright Products', N'Quality Agreement', N'Executed', NULL, NULL, N'Annual renewal', 1, 90, 0, N'IFF / HealthWright Products'),
        (N'CTR-2026-007', N'Pharmako BioBerb TMLA (NutraAxis)', N'Pharmako Biotechnologies', N'TMLA', N'Executed', NULL, CAST(N'2026-04-28' AS DATE), NULL, 0, NULL, NULL, NULL),
        (N'CTR-2026-008', N'Pharmako TRPTI TMLA (NutraAxis / Saanroo)', N'Pharmako / Saanroo Health / Tripti', N'TMLA', N'Executed', NULL, NULL, N'Review', 0, NULL, NULL, NULL),
        (N'CTR-2026-009', N'Capo Commerce Master Services Agreement', N'Capo Commerce', N'MSA', N'Executed', NULL, CAST(N'2026-04-30' AS DATE), NULL, 0, NULL, NULL, NULL),
        (N'CTR-2026-010', N'Capo Commerce ACCS Replatforming SOW', N'Capo Commerce', N'SOW / Consulting', N'Executed', NULL, NULL, N'Project-based', 0, NULL, NULL, NULL),
        (N'CTR-2026-011', N'CartdotCom Client Master Services Agreement', N'Cart.com', N'MSA', N'Under Negotiation', NULL, NULL, N'TBD', 0, NULL, NULL, NULL),
        (N'CTR-2026-012', N'CartdotCom FaaS SOW', N'Cart.com', N'SOW / Consulting', N'Under Negotiation', NULL, NULL, N'TBD', 0, NULL, NULL, NULL),
        (N'CTR-2026-013', N'Avalara Sales Tax Service Agreement', N'Avalara', N'Tax Service', N'Executed', NULL, CAST(N'2026-12-16' AS DATE), NULL, 0, NULL, NULL, NULL),
        (N'CTR-2026-014', N'MTL Lab Services Agreement', N'Molecular Testing Labs (MTL)', N'Lab Services', N'Executed', NULL, NULL, N'Review', 0, NULL, NULL, NULL),
        (N'CTR-2026-015', N'MTL SOW – NFC Wells Pharmacy', N'Molecular Testing Labs', N'SOW / Consulting', N'Executed', NULL, NULL, N'Review', 0, NULL, NULL, NULL),
        (N'CTR-2026-016', N'HealNow/TABZ Partnership Agreement', N'HealNow Inc. dba Tabz', N'Partnership', N'Under Negotiation', NULL, NULL, N'TBD', 0, NULL, NULL, NULL),
        (N'CTR-2026-017', N'Leverture Azure Integration SOW', N'Leverture', N'SOW / Consulting', N'Active', NULL, NULL, N'Review', 0, NULL, NULL, NULL),
        (N'CTR-2026-018', N'IntraEdge Education Pilot Proposal', N'IntraEdge', N'SOW / Consulting', N'Under Negotiation', NULL, NULL, N'TBD', 0, NULL, NULL, NULL),
        (N'CTR-2026-019', N'WPN General MNDA (Signed – multiple parties)', N'Various (Hayley Miller, Intertek, etc.)', N'NDA/MNDA', N'Executed', NULL, NULL, N'Ongoing', 0, NULL, 0, NULL)
) AS source (
    ContractNumber,
    ContractName,
    Counterparty,
    ContractType,
    ContractStatus,
    EffectiveDate,
    ExpirationDate,
    ExpirationNotes,
    AutoRenewal,
    RenewalNoticeDays,
    AnnualValue,
    RelatedSupplier
)
    ON target.ContractNumber = source.ContractNumber
WHEN MATCHED THEN
    UPDATE SET
        ContractName = source.ContractName,
        Counterparty = source.Counterparty,
        ContractType = source.ContractType,
        ContractStatus = source.ContractStatus,
        EffectiveDate = source.EffectiveDate,
        ExpirationDate = source.ExpirationDate,
        ExpirationNotes = source.ExpirationNotes,
        AutoRenewal = source.AutoRenewal,
        RenewalNoticeDays = source.RenewalNoticeDays,
        AnnualValue = source.AnnualValue,
        RelatedSupplier = source.RelatedSupplier,
        ModifiedDate = SYSUTCDATETIME(),
        ModifiedbyUser = 1
WHEN NOT MATCHED BY TARGET THEN
    INSERT (
        ContractNumber,
        ContractName,
        Counterparty,
        ContractType,
        ContractStatus,
        EffectiveDate,
        ExpirationDate,
        ExpirationNotes,
        AutoRenewal,
        RenewalNoticeDays,
        AnnualValue,
        RelatedSupplier,
        ModifiedbyUser
    )
    VALUES (
        source.ContractNumber,
        source.ContractName,
        source.Counterparty,
        source.ContractType,
        source.ContractStatus,
        source.EffectiveDate,
        source.ExpirationDate,
        source.ExpirationNotes,
        source.AutoRenewal,
        source.RenewalNoticeDays,
        source.AnnualValue,
        source.RelatedSupplier,
        1
    );
GO
