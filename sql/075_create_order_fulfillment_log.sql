/*
  NutraAxis — webhook idempotency for ACCS order → Service Bus publish (test + prod)
*/

IF OBJECT_ID(N'dbo.OrderFulfillmentLog', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.OrderFulfillmentLog (
        OrderFulfillmentLogID   INT             NOT NULL IDENTITY(1,1),
        AccsEntityId            INT             NOT NULL,
        IncrementId             NVARCHAR(50)    NULL,
        SourceEnvironment       NVARCHAR(20)    NOT NULL,
        WebhookReceivedAt       DATETIME2(0)    NOT NULL CONSTRAINT DF_OrderFulfillmentLog_Received DEFAULT (SYSUTCDATETIME()),
        PublishedToTopicAt      DATETIME2(0)    NULL,
        ServiceBusMessageId     NVARCHAR(128)   NULL,
        TopicName               NVARCHAR(200)   NULL,
        Status                  NVARCHAR(30)    NOT NULL,
        LastError               NVARCHAR(1000)  NULL,

        CONSTRAINT PK_OrderFulfillmentLog PRIMARY KEY CLUSTERED (OrderFulfillmentLogID),
        CONSTRAINT UQ_OrderFulfillmentLog_Entity UNIQUE (AccsEntityId, SourceEnvironment)
    );

    CREATE NONCLUSTERED INDEX IX_OrderFulfillmentLog_IncrementId
        ON dbo.OrderFulfillmentLog (IncrementId, SourceEnvironment);
END;
GO
