/*
  NutraAxis Operations — Jazz OMS send tracking on facility transfers
*/

IF COL_LENGTH('dbo.InvTransfer', 'JazzShipmentNumber') IS NULL
    ALTER TABLE dbo.InvTransfer ADD JazzShipmentNumber NVARCHAR(50) NULL;

IF COL_LENGTH('dbo.InvTransfer', 'JazzAsnId') IS NULL
    ALTER TABLE dbo.InvTransfer ADD JazzAsnId NVARCHAR(50) NULL;

IF COL_LENGTH('dbo.InvTransfer', 'JazzAsnStatus') IS NULL
    ALTER TABLE dbo.InvTransfer ADD JazzAsnStatus NVARCHAR(30) NULL;

IF COL_LENGTH('dbo.InvTransfer', 'JazzSendStatus') IS NULL
    ALTER TABLE dbo.InvTransfer ADD JazzSendStatus NVARCHAR(20) NULL;

IF COL_LENGTH('dbo.InvTransfer', 'JazzSendError') IS NULL
    ALTER TABLE dbo.InvTransfer ADD JazzSendError NVARCHAR(500) NULL;

IF COL_LENGTH('dbo.InvTransfer', 'JazzSentAt') IS NULL
    ALTER TABLE dbo.InvTransfer ADD JazzSentAt DATETIME2(0) NULL;
GO