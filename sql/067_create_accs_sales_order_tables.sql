/*
  NutraAxis Operations — ACCS Sales Order ingest tables (dbo.nutraaxis)

  Maps Adobe Commerce REST API Sales Order resources:
    - GET /V1/orders, GET /V1/orders/{id}  → dbo.AccsSalesOrderHeader
    - order.items[]                          → dbo.AccsSalesOrderDetail

  Source environment distinguishes production vs stage tenants during ingest.
*/

IF OBJECT_ID(N'dbo.AccsSalesOrderHeader', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.AccsSalesOrderHeader (
        AccsSalesOrderHeaderID  INT             NOT NULL IDENTITY(1,1),

        /* ACCS identity — entity_id, increment_id */
        AccsEntityId            INT             NOT NULL,
        IncrementId             NVARCHAR(50)    NOT NULL,
        AccsState               NVARCHAR(50)    NULL,
        OrderStatus             NVARCHAR(50)    NOT NULL,

        /* created_at, updated_at */
        OrderCreatedAt          DATETIME2(0)    NOT NULL,
        OrderUpdatedAt          DATETIME2(0)    NULL,

        /* Customer — customer_id, customer_email, customer_firstname, customer_lastname */
        CustomerId              INT             NULL,
        CustomerEmail           NVARCHAR(254)   NULL,
        CustomerFirstName       NVARCHAR(100)   NULL,
        CustomerLastName        NVARCHAR(100)   NULL,
        CustomerGroupId         INT             NULL,
        CustomerIsGuest         BIT             NOT NULL CONSTRAINT DF_AccsSalesOrderHeader_CustomerIsGuest DEFAULT (0),

        /* Store — store_id, store_name */
        StoreId                 INT             NULL,
        StoreName               NVARCHAR(300)   NULL,

        /* Currency — order_currency_code, base_currency_code */
        OrderCurrencyCode       NVARCHAR(3)     NULL,
        BaseCurrencyCode        NVARCHAR(3)     NULL,

        /* Order totals (order currency) */
        Subtotal                DECIMAL(18,4)   NULL,
        SubtotalInclTax         DECIMAL(18,4)   NULL,
        ShippingAmount          DECIMAL(18,4)   NULL,
        ShippingInclTax         DECIMAL(18,4)   NULL,
        ShippingDescription     NVARCHAR(500)   NULL,
        ShippingTaxAmount       DECIMAL(18,4)   NULL,
        TaxAmount               DECIMAL(18,4)   NULL,
        DiscountAmount          DECIMAL(18,4)   NULL,
        GrandTotal              DECIMAL(18,4)   NULL,
        TotalDue                DECIMAL(18,4)   NULL,
        TotalPaid               DECIMAL(18,4)   NULL,
        TotalInvoiced           DECIMAL(18,4)   NULL,
        TotalRefunded           DECIMAL(18,4)   NULL,
        TotalOnlineRefunded     DECIMAL(18,4)   NULL,
        Weight                  DECIMAL(18,4)   NULL,
        TotalQtyOrdered         DECIMAL(18,4)   NULL,
        TotalItemCount          INT             NULL,

        /* payment.method */
        PaymentMethod           NVARCHAR(100)   NULL,

        /* billing_address */
        BillingAddressId        INT             NULL,
        BillFirstName           NVARCHAR(100)   NULL,
        BillLastName            NVARCHAR(100)   NULL,
        BillCompany             NVARCHAR(200)   NULL,
        BillStreet1             NVARCHAR(200)   NULL,
        BillStreet2             NVARCHAR(200)   NULL,
        BillCity                NVARCHAR(100)   NULL,
        BillRegion              NVARCHAR(100)   NULL,
        BillRegionCode          NVARCHAR(20)    NULL,
        BillPostcode            NVARCHAR(20)    NULL,
        BillCountryId           NVARCHAR(10)    NULL,
        BillTelephone           NVARCHAR(50)    NULL,
        BillEmail               NVARCHAR(254)   NULL,

        /* shipping_assignments[0].shipping.address or extension shipping address */
        ShippingAddressId       INT             NULL,
        ShipFirstName           NVARCHAR(100)   NULL,
        ShipLastName            NVARCHAR(100)   NULL,
        ShipCompany             NVARCHAR(200)   NULL,
        ShipStreet1             NVARCHAR(200)   NULL,
        ShipStreet2             NVARCHAR(200)   NULL,
        ShipCity                NVARCHAR(100)   NULL,
        ShipRegion              NVARCHAR(100)   NULL,
        ShipRegionCode          NVARCHAR(20)    NULL,
        ShipPostcode            NVARCHAR(20)    NULL,
        ShipCountryId           NVARCHAR(10)    NULL,
        ShipTelephone           NVARCHAR(50)    NULL,
        ShipEmail               NVARCHAR(254)   NULL,

        /* Misc header attributes */
        QuoteId                 INT             NULL,
        RemoteIp                NVARCHAR(45)    NULL,
        IsVirtual               BIT             NOT NULL CONSTRAINT DF_AccsSalesOrderHeader_IsVirtual DEFAULT (0),
        EmailSent               BIT             NOT NULL CONSTRAINT DF_AccsSalesOrderHeader_EmailSent DEFAULT (0),

        /* Ingest bookkeeping */
        SourceEnvironment       NVARCHAR(20)    NOT NULL CONSTRAINT DF_AccsSalesOrderHeader_Source DEFAULT (N'production'),
        ImportedAt              DATETIME2(0)    NOT NULL CONSTRAINT DF_AccsSalesOrderHeader_ImportedAt DEFAULT (SYSUTCDATETIME()),
        LastSyncedAt            DATETIME2(0)    NOT NULL CONSTRAINT DF_AccsSalesOrderHeader_LastSyncedAt DEFAULT (SYSUTCDATETIME()),
        RawPayloadJson          NVARCHAR(MAX)   NULL,

        CONSTRAINT PK_AccsSalesOrderHeader PRIMARY KEY CLUSTERED (AccsSalesOrderHeaderID),
        CONSTRAINT UQ_AccsSalesOrderHeader_Entity UNIQUE (AccsEntityId, SourceEnvironment),
        CONSTRAINT UQ_AccsSalesOrderHeader_Increment UNIQUE (IncrementId, SourceEnvironment),
        CONSTRAINT CK_AccsSalesOrderHeader_SourceEnvironment CHECK (
            SourceEnvironment IN (N'production', N'stage')
        )
    );

    CREATE NONCLUSTERED INDEX IX_AccsSalesOrderHeader_OrderCreatedAt
        ON dbo.AccsSalesOrderHeader (OrderCreatedAt DESC, SourceEnvironment);

    CREATE NONCLUSTERED INDEX IX_AccsSalesOrderHeader_OrderStatus
        ON dbo.AccsSalesOrderHeader (OrderStatus, SourceEnvironment);

    CREATE NONCLUSTERED INDEX IX_AccsSalesOrderHeader_CustomerEmail
        ON dbo.AccsSalesOrderHeader (CustomerEmail, SourceEnvironment);
END;
GO

IF OBJECT_ID(N'dbo.AccsSalesOrderDetail', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.AccsSalesOrderDetail (
        AccsSalesOrderDetailID  INT             NOT NULL IDENTITY(1,1),
        AccsSalesOrderHeaderID  INT             NOT NULL,

        /* items[].item_id, items[].order_id */
        AccsItemId              INT             NOT NULL,
        AccsOrderEntityId       INT             NOT NULL,
        LineNumber              INT             NOT NULL,

        /* items[].sku, name, product_id, product_type */
        SKU                     NVARCHAR(100)   NOT NULL,
        ProductName             NVARCHAR(300)   NULL,
        ProductId               INT             NULL,
        ProductType             NVARCHAR(50)    NULL,
        Description             NVARCHAR(500)   NULL,

        /* Quantity fields — qty_ordered, qty_shipped, qty_invoiced, qty_canceled, qty_refunded, qty_returned */
        QtyOrdered              DECIMAL(18,4)   NOT NULL CONSTRAINT DF_AccsSalesOrderDetail_QtyOrdered DEFAULT (0),
        QtyShipped              DECIMAL(18,4)   NOT NULL CONSTRAINT DF_AccsSalesOrderDetail_QtyShipped DEFAULT (0),
        QtyInvoiced             DECIMAL(18,4)   NOT NULL CONSTRAINT DF_AccsSalesOrderDetail_QtyInvoiced DEFAULT (0),
        QtyCanceled             DECIMAL(18,4)   NOT NULL CONSTRAINT DF_AccsSalesOrderDetail_QtyCanceled DEFAULT (0),
        QtyRefunded             DECIMAL(18,4)   NOT NULL CONSTRAINT DF_AccsSalesOrderDetail_QtyRefunded DEFAULT (0),
        QtyReturned             DECIMAL(18,4)   NOT NULL CONSTRAINT DF_AccsSalesOrderDetail_QtyReturned DEFAULT (0),

        /* Pricing (order currency) — original_price, price, price_incl_tax, row_total, row_total_incl_tax */
        OriginalPrice           DECIMAL(18,4)   NULL,
        UnitPrice               DECIMAL(18,4)   NULL,
        UnitPriceInclTax        DECIMAL(18,4)   NULL,
        RowTotal                DECIMAL(18,4)   NULL,
        RowTotalInclTax         DECIMAL(18,4)   NULL,
        RowInvoiced             DECIMAL(18,4)   NULL,
        DiscountAmount          DECIMAL(18,4)   NULL,
        DiscountPercent         DECIMAL(9,4)    NULL,
        TaxAmount               DECIMAL(18,4)   NULL,
        TaxPercent              DECIMAL(9,4)    NULL,
        BaseCost                DECIMAL(18,4)   NULL,

        /* Physical — weight, row_weight */
        Weight                  DECIMAL(18,4)   NULL,
        RowWeight               DECIMAL(18,4)   NULL,
        IsVirtual               BIT             NOT NULL CONSTRAINT DF_AccsSalesOrderDetail_IsVirtual DEFAULT (0),
        IsQtyDecimal            BIT             NOT NULL CONSTRAINT DF_AccsSalesOrderDetail_IsQtyDecimal DEFAULT (0),
        FreeShipping            BIT             NOT NULL CONSTRAINT DF_AccsSalesOrderDetail_FreeShipping DEFAULT (0),

        /* Enriched from ACCS product fulfillment custom attribute */
        FulfillmentAttr         NVARCHAR(50)    NULL,
        SupplierCode            NVARCHAR(50)    NULL,

        /* Configurable/bundle child lines — parent_item_id */
        ParentAccsItemId        INT             NULL,

        StoreId                 INT             NULL,
        ItemCreatedAt           DATETIME2(0)    NULL,
        ItemUpdatedAt           DATETIME2(0)    NULL,

        SourceEnvironment       NVARCHAR(20)    NOT NULL CONSTRAINT DF_AccsSalesOrderDetail_Source DEFAULT (N'production'),
        LastSyncedAt            DATETIME2(0)    NOT NULL CONSTRAINT DF_AccsSalesOrderDetail_LastSyncedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_AccsSalesOrderDetail PRIMARY KEY CLUSTERED (AccsSalesOrderDetailID),
        CONSTRAINT UQ_AccsSalesOrderDetail_Item UNIQUE (AccsItemId, SourceEnvironment),
        CONSTRAINT CK_AccsSalesOrderDetail_SourceEnvironment CHECK (
            SourceEnvironment IN (N'production', N'stage')
        ),
        CONSTRAINT FK_AccsSalesOrderDetail_Header FOREIGN KEY (AccsSalesOrderHeaderID)
            REFERENCES dbo.AccsSalesOrderHeader (AccsSalesOrderHeaderID) ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX IX_AccsSalesOrderDetail_HeaderID
        ON dbo.AccsSalesOrderDetail (AccsSalesOrderHeaderID, LineNumber);

    CREATE NONCLUSTERED INDEX IX_AccsSalesOrderDetail_SKU
        ON dbo.AccsSalesOrderDetail (SKU, SourceEnvironment);

    CREATE NONCLUSTERED INDEX IX_AccsSalesOrderDetail_Fulfillment
        ON dbo.AccsSalesOrderDetail (FulfillmentAttr, SupplierCode)
        WHERE FulfillmentAttr IS NOT NULL;
END;
GO
