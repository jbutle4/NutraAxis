-- Simulate Jazz receive for UAT E2E PO receipt PORID 22 (Jazz ASN 7131).
-- QTY REC already set on lines; unblock Inventory Receipt Sync.

UPDATE dbo.POReceipt
SET JazzASNStatus = N'received',
    ActualReceiptDate = CAST(SYSUTCDATETIME() AS DATE),
    JazzReceivedAt = SYSUTCDATETIME()
WHERE PORID = 22;

SELECT PORID, PORStatus, JazzASN, JazzASNStatus, ActualReceiptDate, JazzReceivedAt, IMSPostedAt
FROM dbo.POReceipt
WHERE PORID = 22;

SELECT PORDID, ItemSKU, QuantityExpected, QuantityReceived
FROM dbo.PORDetail
WHERE PORID = 22;
