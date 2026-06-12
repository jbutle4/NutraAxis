/*
  Move AddressType from AlertMessage to AlertSubscription (per-user TO/CC).
*/

IF COL_LENGTH('dbo.AlertSubscription', 'AddressType') IS NULL
BEGIN
    ALTER TABLE dbo.AlertSubscription
        ADD AddressType NVARCHAR(10) NOT NULL
            CONSTRAINT DF_AlertSubscription_AddressType DEFAULT (N'TO');
END;
GO

IF COL_LENGTH('dbo.AlertMessage', 'AddressType') IS NOT NULL
BEGIN
    UPDATE sub
    SET sub.AddressType = am.AddressType
    FROM dbo.AlertSubscription sub
    INNER JOIN dbo.AlertMessage am ON am.alertID = sub.alertID
    WHERE sub.AddressType = N'TO';
END;
GO

IF COL_LENGTH('dbo.AlertMessage', 'AddressType') IS NOT NULL
BEGIN
    IF OBJECT_ID(N'dbo.UQ_AlertMessage_Name_AddressType', N'UQ') IS NOT NULL
        ALTER TABLE dbo.AlertMessage DROP CONSTRAINT UQ_AlertMessage_Name_AddressType;

    IF OBJECT_ID(N'dbo.CK_AlertMessage_AddressType', N'C') IS NOT NULL
        ALTER TABLE dbo.AlertMessage DROP CONSTRAINT CK_AlertMessage_AddressType;

    ALTER TABLE dbo.AlertMessage DROP COLUMN AddressType;
END;
GO

IF OBJECT_ID(N'dbo.UQ_AlertMessage_AlertName', N'UQ') IS NULL
    ALTER TABLE dbo.AlertMessage
        ADD CONSTRAINT UQ_AlertMessage_AlertName UNIQUE (AlertName);
GO

IF OBJECT_ID(N'dbo.CK_AlertSubscription_AddressType', N'C') IS NULL
    ALTER TABLE dbo.AlertSubscription
        ADD CONSTRAINT CK_AlertSubscription_AddressType
        CHECK (AddressType IN (N'TO', N'CC'));
GO
