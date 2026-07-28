/*
  NutraAxis Operations — Clone PO 16 and PO 13 for IMS UAT receiving tests.
  Copies headers + line items only (no receipts, QBO links, or approval history).
  Idempotent on PONumber (NS20251121-UAT / NS20251111-UAT).
*/

DECLARE @Clones TABLE (
    SourcePOID INT NOT NULL,
    NewPONumber NVARCHAR(50) NOT NULL
);

INSERT INTO @Clones (SourcePOID, NewPONumber)
VALUES
    (16, N'NS20251121-UAT'),
    (13, N'NS20251111-UAT');

DECLARE @SourcePOID INT;
DECLARE @NewPONumber NVARCHAR(50);
DECLARE @NewPOID INT;

DECLARE clone_cursor CURSOR LOCAL FAST_FORWARD FOR
    SELECT SourcePOID, NewPONumber FROM @Clones;

OPEN clone_cursor;
FETCH NEXT FROM clone_cursor INTO @SourcePOID, @NewPONumber;

WHILE @@FETCH_STATUS = 0
BEGIN
    IF EXISTS (SELECT 1 FROM dbo.PurchaseOrder WHERE PONumber = @NewPONumber)
    BEGIN
        SELECT @NewPOID = POID
        FROM dbo.PurchaseOrder
        WHERE PONumber = @NewPONumber;

        PRINT N'UAT PO already exists: ' + @NewPONumber + N' (POID ' + CAST(@NewPOID AS NVARCHAR(20)) + N') — skipping.';
    END
    ELSE IF NOT EXISTS (SELECT 1 FROM dbo.PurchaseOrder WHERE POID = @SourcePOID)
    BEGIN
        PRINT N'Source POID ' + CAST(@SourcePOID AS NVARCHAR(20)) + N' not found — skipping ' + @NewPONumber + N'.';
    END
    ELSE
    BEGIN
        INSERT INTO dbo.PurchaseOrder (
            PONumber,
            SupplierID,
            POStatus,
            OrderDate,
            ExpectedDeliveryDate,
            Notes,
            Subtotal,
            CreatedByUser,
            ModifiedbyUser,
            BuyerName,
            BuyerAddress,
            BuyerContactName,
            BuyerContactEmail,
            BuyerContactPhone,
            SupplierAddress,
            PaymentTerms,
            DeliveryTerms,
            ReferenceDocuments,
            ShippingHandling,
            TotalDue,
            SpecialInstructions,
            DeliveryAddress,
            QBO_POID,
            POQBOCreated,
            ApprovedTotalDue,
            ApprovedAt,
            RequiresReapproval,
            POType
        )
        SELECT
            @NewPONumber,
            src.SupplierID,
            N'Approved',
            CAST(SYSUTCDATETIME() AS DATE),
            src.ExpectedDeliveryDate,
            COALESCE(src.Notes, N'') + CASE WHEN src.Notes IS NULL OR LTRIM(RTRIM(src.Notes)) = N'' THEN N'' ELSE N' | ' END
                + N'UAT clone of POID ' + CAST(src.POID AS NVARCHAR(20)) + N' (' + src.PONumber + N').',
            src.Subtotal,
            src.CreatedByUser,
            src.ModifiedbyUser,
            src.BuyerName,
            src.BuyerAddress,
            src.BuyerContactName,
            src.BuyerContactEmail,
            src.BuyerContactPhone,
            src.SupplierAddress,
            src.PaymentTerms,
            src.DeliveryTerms,
            src.ReferenceDocuments,
            src.ShippingHandling,
            src.TotalDue,
            src.SpecialInstructions,
            src.DeliveryAddress,
            NULL,
            0,
            NULL,
            NULL,
            0,
            COALESCE(src.POType, N'Inventory')
        FROM dbo.PurchaseOrder src
        WHERE src.POID = @SourcePOID;

        SET @NewPOID = SCOPE_IDENTITY();

        DECLARE @LineCount INT;

        INSERT INTO dbo.POLineItem (
            POID,
            LineNumber,
            ItemSKU,
            ItemDescription,
            Quantity,
            UnitPrice,
            QuantityReceived,
            QuoteNumber,
            ExpirationDate
        )
        SELECT
            @NewPOID,
            li.LineNumber,
            li.ItemSKU,
            li.ItemDescription,
            li.Quantity,
            li.UnitPrice,
            0,
            li.QuoteNumber,
            li.ExpirationDate
        FROM dbo.POLineItem li
        WHERE li.POID = @SourcePOID
        ORDER BY li.LineNumber;

        SET @LineCount = @@ROWCOUNT;

        PRINT N'Created ' + @NewPONumber + N' as POID ' + CAST(@NewPOID AS NVARCHAR(20))
            + N' from source POID ' + CAST(@SourcePOID AS NVARCHAR(20))
            + N' (' + CAST(@LineCount AS NVARCHAR(20)) + N' lines).';
    END;

    FETCH NEXT FROM clone_cursor INTO @SourcePOID, @NewPONumber;
END;

CLOSE clone_cursor;
DEALLOCATE clone_cursor;
GO

SELECT
    po.POID,
    po.PONumber,
    po.POStatus,
    po.SupplierID,
    s.SupplierName,
    po.Subtotal,
    po.POQBOCreated,
    po.QBO_POID,
    (SELECT COUNT(*) FROM dbo.POLineItem li WHERE li.POID = po.POID) AS LineCount,
    (SELECT COUNT(*) FROM dbo.POReceipt r WHERE r.POID = po.POID) AS ReceiptCount
FROM dbo.PurchaseOrder po
LEFT JOIN dbo.Supplier s ON s.SupplierID = po.SupplierID
WHERE po.PONumber IN (N'NS20251121-UAT', N'NS20251111-UAT')
ORDER BY po.POID;
GO
