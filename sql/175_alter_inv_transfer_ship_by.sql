/*
  Optional Jazz ship-by date for facility transfer order/import (YYYY-MM-DD).
*/
IF COL_LENGTH('dbo.InvTransfer', 'JazzShipByDate') IS NULL
    ALTER TABLE dbo.InvTransfer ADD JazzShipByDate DATE NULL;

GO