/*
  NutraAxis Operations — PO receipt line lot numbers (multiple lots per PO line).
*/

IF COL_LENGTH('dbo.PORDetail', 'LotNumber') IS NULL
    ALTER TABLE dbo.PORDetail ADD LotNumber NVARCHAR(50) NULL;
GO
