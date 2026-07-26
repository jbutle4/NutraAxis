/*
  NutraAxis Operations — Sandbox smoke order for Inventory Sales Sync
  Inserts a stage ACCS order with 2 shipped lines against SKUs that already
  have CART IMS qty from the Jazz ASN receipt simulation (NA-MT-004 / NA-HR-006).
  Qty = 2 each (not full available). Idempotent on IncrementId + SourceEnvironment.
*/

DECLARE @SourceEnv NVARCHAR(20) = N'stage';
DECLARE @IncrementId NVARCHAR(50) = N'NA-SMOKE-SAL-001';
DECLARE @EntityId INT = 900001;
DECLARE @HeaderId INT;

IF EXISTS (
    SELECT 1
    FROM dbo.AccsSalesOrderHeader
    WHERE IncrementId = @IncrementId
      AND SourceEnvironment = @SourceEnv
)
BEGIN
    PRINT N'Smoke sales order already exists — skipping insert.';
END
ELSE
BEGIN
    INSERT INTO dbo.AccsSalesOrderHeader (
        AccsEntityId,
        IncrementId,
        AccsState,
        OrderStatus,
        OrderCreatedAt,
        OrderUpdatedAt,
        CustomerEmail,
        CustomerFirstName,
        CustomerLastName,
        CustomerIsGuest,
        OrderCurrencyCode,
        BaseCurrencyCode,
        GrandTotal,
        TotalQtyOrdered,
        TotalItemCount,
        SourceEnvironment,
        RawPayloadJson
    )
    VALUES (
        @EntityId,
        @IncrementId,
        N'complete',
        N'complete',
        SYSUTCDATETIME(),
        SYSUTCDATETIME(),
        N'sales-sync-smoke@nutraaxis.local',
        N'Sandbox',
        N'SalesSmoke',
        1,
        N'USD',
        N'USD',
        0,
        4,
        2,
        @SourceEnv,
        N'{"smoke":true,"purpose":"inventory-sales-sync"}'
    );

    SET @HeaderId = SCOPE_IDENTITY();

    INSERT INTO dbo.AccsSalesOrderDetail (
        AccsSalesOrderHeaderID,
        AccsItemId,
        AccsOrderEntityId,
        LineNumber,
        SKU,
        ProductName,
        QtyOrdered,
        QtyShipped,
        QtyInvoiced,
        FulfillmentAttr,
        SourceEnvironment
    )
    VALUES
        (@HeaderId, 900001, @EntityId, 1, N'NA-MT-004', N'Sandbox smoke MT', 2, 2, 2, N'CART', @SourceEnv),
        (@HeaderId, 900002, @EntityId, 2, N'NA-HR-006', N'Sandbox smoke HR', 2, 2, 2, N'CART', @SourceEnv);

    PRINT N'Inserted smoke sales order ' + @IncrementId + N' header ' + CAST(@HeaderId AS NVARCHAR(20));
END;
GO
