/*
  NutraAxis Operations — QBO connection-down alert cooldown + alert catalog entry
*/

IF COL_LENGTH('dbo.QBOConnection', 'LastDisconnectAlertAt') IS NULL
    ALTER TABLE dbo.QBOConnection ADD LastDisconnectAlertAt DATETIME2(0) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.AlertMessage WHERE AlertName = N'qbo-connection-down')
    INSERT INTO dbo.AlertMessage (AlertName, AlertStatus, AlertDescription)
    VALUES (
        N'qbo-connection-down',
        1,
        N'QuickBooks Sandbox or Production disconnected or token refresh failed — reconnect on Accounting home.'
    );
GO
