/*
  Replace NA-LV-010 with NA-LV-012 in PO and PO Receiving line tables only.
  SKUMaster is intentionally unchanged.
*/

UPDATE dbo.POLineItem
SET ItemSKU = N'NA-LV-012'
WHERE ItemSKU = N'NA-LV-010';
GO

UPDATE dbo.PORDetail
SET
    ItemSKU = N'NA-LV-012',
    SKUBarcode = (
        SELECT NULLIF(LTRIM(RTRIM(s.UPC)), N'')
        FROM dbo.SKUMaster s
        WHERE s.SKUCode = N'NA-LV-012'
    )
WHERE ItemSKU = N'NA-LV-010';
GO
