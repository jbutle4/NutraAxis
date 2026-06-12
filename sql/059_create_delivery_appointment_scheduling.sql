/*
  NutraAxis Operations — Delivery appointment scheduling
*/

IF OBJECT_ID(N'dbo.DeliveryAppointmentScheduling', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.DeliveryAppointmentScheduling (
        ApptID                  INT             NOT NULL IDENTITY(1,1),
        POReceiptID             INT             NULL,
        POID                    INT             NOT NULL,
        CreateDate              DATETIME2(0)    NOT NULL CONSTRAINT DF_DeliveryAppt_CreateDate DEFAULT (SYSUTCDATETIME()),
        CreatedBy               NVARCHAR(200)   NULL,
        ModifiedDate            DATETIME2(0)    NOT NULL CONSTRAINT DF_DeliveryAppt_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedBy              NVARCHAR(200)   NULL,
        SupplierID              INT             NOT NULL,
        CompanyName             NVARCHAR(200)   NULL,
        ContactEmail            NVARCHAR(200)   NULL,
        ContactName             NVARCHAR(200)   NULL,
        ContactPhone            NVARCHAR(50)    NULL,
        AppointmentDateTime     DATETIME2(0)    NULL,
        AppointmentAddress      NVARCHAR(500)   NULL,
        AppointmentCompanyName  NVARCHAR(200)   NULL,
        AppointmentStatus       NVARCHAR(30)    NOT NULL CONSTRAINT DF_DeliveryAppt_Status DEFAULT (N'Not Scheduled'),
        AppointmentASNCreated   BIT             NOT NULL CONSTRAINT DF_DeliveryAppt_ASNCreated DEFAULT (0),
        AppointmentASNNumber    NVARCHAR(50)    NULL,
        AppointmentNotes        NVARCHAR(MAX)   NULL,

        CONSTRAINT PK_DeliveryAppointmentScheduling PRIMARY KEY CLUSTERED (ApptID),
        CONSTRAINT FK_DeliveryAppt_POReceipt FOREIGN KEY (POReceiptID)
            REFERENCES dbo.POReceipt (PORID),
        CONSTRAINT FK_DeliveryAppt_PurchaseOrder FOREIGN KEY (POID)
            REFERENCES dbo.PurchaseOrder (POID),
        CONSTRAINT FK_DeliveryAppt_Supplier FOREIGN KEY (SupplierID)
            REFERENCES dbo.Supplier (SupplierID),
        CONSTRAINT CK_DeliveryAppt_Status CHECK (
            AppointmentStatus IN (N'Not Scheduled', N'Scheduled', N'Canceled')
        )
    );

    CREATE NONCLUSTERED INDEX IX_DeliveryAppt_POReceiptID
        ON dbo.DeliveryAppointmentScheduling (POReceiptID)
        WHERE POReceiptID IS NOT NULL;

    CREATE NONCLUSTERED INDEX IX_DeliveryAppt_POID
        ON dbo.DeliveryAppointmentScheduling (POID);

    CREATE NONCLUSTERED INDEX IX_DeliveryAppt_SupplierID
        ON dbo.DeliveryAppointmentScheduling (SupplierID);

    CREATE NONCLUSTERED INDEX IX_DeliveryAppt_AppointmentStatus
        ON dbo.DeliveryAppointmentScheduling (AppointmentStatus);

    CREATE NONCLUSTERED INDEX IX_DeliveryAppt_AppointmentDateTime
        ON dbo.DeliveryAppointmentScheduling (AppointmentDateTime)
        WHERE AppointmentDateTime IS NOT NULL;
END;
GO
