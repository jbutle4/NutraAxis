/*
  NutraAxis Operations — Support role for Zendesk ticket access

  Support = R  → view own tickets only (non-agent requesters)
  Support = CRUD (or RU+) → Zendesk agent access in Operations (all tickets, replies, status)
*/

MERGE dbo.Role AS target
USING (
    VALUES (
        N'Support',
        N'View Zendesk support tickets submitted under the user''s NutraAxis email. Assign Update for agent-level ticket management.',
        N'R'
    )
) AS source (RoleName, RoleDesc, SupportPermission)
    ON target.RoleName = source.RoleName
WHEN MATCHED THEN
    UPDATE SET
        RoleDesc = source.RoleDesc,
        Support = source.SupportPermission,
        OperationsDashboard = COALESCE(target.OperationsDashboard, N'R'),
        ModifiedbyUser = 1
WHEN NOT MATCHED BY TARGET THEN
    INSERT (
        RoleName,
        RoleDesc,
        RoleCreateDate,
        ModifiedbyUser,
        Support,
        OperationsDashboard
    )
    VALUES (
        source.RoleName,
        source.RoleDesc,
        SYSUTCDATETIME(),
        1,
        source.SupportPermission,
        N'R'
    );
GO
