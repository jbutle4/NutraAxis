/*
  NutraAxis Operations — configurable outbound alert messages and user subscriptions
*/

IF OBJECT_ID(N'dbo.AlertMessage', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.AlertMessage (
        alertID            INT             NOT NULL IDENTITY(1,1),
        AlertName          NVARCHAR(100)   NOT NULL,
        AlertStatus        BIT             NOT NULL CONSTRAINT DF_AlertMessage_AlertStatus DEFAULT (1),
        AlertDescription   NVARCHAR(500)   NULL,
        AddressType        NVARCHAR(10)    NOT NULL,

        CONSTRAINT PK_AlertMessage PRIMARY KEY CLUSTERED (alertID),
        CONSTRAINT UQ_AlertMessage_Name_AddressType UNIQUE (AlertName, AddressType),
        CONSTRAINT CK_AlertMessage_AddressType CHECK (AddressType IN (N'TO', N'CC'))
    );
END;
GO

IF OBJECT_ID(N'dbo.AlertSubscription', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.AlertSubscription (
        alertSubID         INT             NOT NULL IDENTITY(1,1),
        alertID            INT             NOT NULL,
        UserID             INT             NOT NULL,

        CONSTRAINT PK_AlertSubscription PRIMARY KEY CLUSTERED (alertSubID),
        CONSTRAINT UQ_AlertSubscription_Alert_User UNIQUE (alertID, UserID),
        CONSTRAINT FK_AlertSubscription_AlertMessage FOREIGN KEY (alertID)
            REFERENCES dbo.AlertMessage (alertID),
        CONSTRAINT FK_AlertSubscription_User FOREIGN KEY (UserID)
            REFERENCES dbo.[User] (UserID)
    );

    CREATE NONCLUSTERED INDEX IX_AlertSubscription_UserID
        ON dbo.AlertSubscription (UserID);
END;
GO

MERGE dbo.AlertMessage AS target
USING (
    SELECT *
    FROM (VALUES
        (N'process-abandoned',    N'Process Abandoned',       N'Background process abandoned after automatic retries are exhausted.', N'TO'),
        (N'po-approval-request',  N'PO Approval Request',     N'Purchase order submitted or resubmitted for approver review.',      N'TO'),
        (N'po-status-update',     N'PO Status Update',        N'Purchase order status changed (approved, rejected, accounting, etc.).', N'TO'),
        (N'po-viewed-by-approver',N'PO Viewed by Approver',   N'Approver opened a submitted purchase order for review.',              N'TO')
    ) AS seed (AlertName, AlertTitle, AlertDescription, AddressType)
) AS source
    ON target.AlertName = source.AlertName
   AND target.AddressType = source.AddressType
WHEN NOT MATCHED BY TARGET THEN
    INSERT (AlertName, AlertStatus, AlertDescription, AddressType)
    VALUES (source.AlertName, 1, source.AlertDescription, source.AddressType);
GO

/* Seed subscriptions from current role-based routing */
INSERT INTO dbo.AlertSubscription (alertID, UserID)
SELECT am.alertID, u.UserID
FROM dbo.AlertMessage am
INNER JOIN dbo.[User] u ON 1 = 1
INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
WHERE am.AlertName = N'po-approval-request'
  AND am.AddressType = N'TO'
  AND am.AlertStatus = 1
  AND r.POApproval LIKE N'%R%'
  AND NOT EXISTS (
      SELECT 1
      FROM dbo.AlertSubscription existing
      WHERE existing.alertID = am.alertID
        AND existing.UserID = u.UserID
  );
GO

INSERT INTO dbo.AlertSubscription (alertID, UserID)
SELECT am.alertID, u.UserID
FROM dbo.AlertMessage am
INNER JOIN dbo.[User] u ON LOWER(u.UserLogin) = N'nutrateam@nfcllc.com'
WHERE am.AlertName IN (N'process-abandoned', N'po-status-update', N'po-viewed-by-approver')
  AND am.AddressType = N'TO'
  AND am.AlertStatus = 1
  AND NOT EXISTS (
      SELECT 1
      FROM dbo.AlertSubscription existing
      WHERE existing.alertID = am.alertID
        AND existing.UserID = u.UserID
  );
GO

INSERT INTO dbo.AlertSubscription (alertID, UserID)
SELECT am.alertID, u.UserID
FROM dbo.AlertMessage am
INNER JOIN dbo.[User] u ON 1 = 1
INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
WHERE am.AlertName = N'po-status-update'
  AND am.AddressType = N'TO'
  AND am.AlertStatus = 1
  AND (r.POManagement LIKE N'%C%' OR r.POManagement LIKE N'%U%')
  AND LOWER(u.UserLogin) <> N'jbutler@nfcllc.com'
  AND NOT EXISTS (
      SELECT 1
      FROM dbo.AlertSubscription existing
      WHERE existing.alertID = am.alertID
        AND existing.UserID = u.UserID
  );
GO
