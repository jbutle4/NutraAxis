/*
  NutraAxis Operations — Product Catalog SKU Master
*/

IF OBJECT_ID(N'dbo.SKUMaster', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.SKUMaster (
        SKUID                       INT             NOT NULL IDENTITY(1,1),
        SKUCode                     NVARCHAR(100)   NOT NULL,
        ProductName                 NVARCHAR(200)   NOT NULL,
        Brand                       NVARCHAR(30)    NOT NULL,
        Manufacturer                NVARCHAR(50)    NOT NULL,
        PrimaryTherapeuticCategory  NVARCHAR(50)    NOT NULL,
        SecondaryCategory           NVARCHAR(50)    NULL,
        SKUStatus                   NVARCHAR(30)    NOT NULL CONSTRAINT DF_SKUMaster_Status DEFAULT (N'In Development'),
        ServingCount                INT             NULL,
        BottleSize                  NVARCHAR(100)   NULL,
        GTIN14                      NVARCHAR(20)    NULL,
        UPC                         NVARCHAR(20)    NULL,
        SupplementFactsPanel        NVARCHAR(200)   NULL,
        Claims                      NVARCHAR(MAX)   NULL,
        AllergenStatement           NVARCHAR(500)   NULL,
        NonGMOCertified             BIT             NOT NULL CONSTRAINT DF_SKUMaster_NonGMO DEFAULT (0),
        COGS                        DECIMAL(18,2)   NULL,
        WholesalePrice              DECIMAL(18,2)   NULL,
        MSRP                        DECIMAL(18,2)   NULL,
        SFPLink                     NVARCHAR(2000)  NULL,
        LabelPrintReadyLink         NVARCHAR(2000)  NULL,
        LaunchDate                  DATE            NULL,
        Notes                       NVARCHAR(MAX)   NULL,
        CreatedByUser               INT             NULL,
        CreateDate                  DATETIME2(0)    NOT NULL CONSTRAINT DF_SKUMaster_CreateDate DEFAULT (SYSUTCDATETIME()),
        ModifiedDate                DATETIME2(0)    NOT NULL CONSTRAINT DF_SKUMaster_ModifiedDate DEFAULT (SYSUTCDATETIME()),
        ModifiedbyUser              INT             NULL,

        CONSTRAINT PK_SKUMaster PRIMARY KEY CLUSTERED (SKUID),
        CONSTRAINT UQ_SKUMaster_SKUCode UNIQUE (SKUCode),
        CONSTRAINT FK_SKUMaster_CreatedByUser FOREIGN KEY (CreatedByUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT FK_SKUMaster_ModifiedByUser FOREIGN KEY (ModifiedbyUser)
            REFERENCES dbo.[User] (UserID),
        CONSTRAINT CK_SKUMaster_Brand CHECK (
            Brand IN (N'NutraSync', N'NutraAxis')
        ),
        CONSTRAINT CK_SKUMaster_Manufacturer CHECK (
            Manufacturer IN (N'NutraSeal', N'VitaQuest', N'IFF-HealthWright', N'Other')
        ),
        CONSTRAINT CK_SKUMaster_PrimaryCategory CHECK (
            PrimaryTherapeuticCategory IN (
                N'Hormonal Support',
                N'GI Health',
                N'Longevity',
                N'Metabolic',
                N'Musculoskeletal',
                N'Cardiovascular',
                N'Sexual Wellness',
                N'Prenatal',
                N'Other'
            )
        ),
        CONSTRAINT CK_SKUMaster_SecondaryCategory CHECK (
            SecondaryCategory IS NULL OR SecondaryCategory IN (
                N'Hormonal Support',
                N'GI Health',
                N'Longevity',
                N'Metabolic',
                N'Musculoskeletal',
                N'Cardiovascular',
                N'Sexual Wellness',
                N'Prenatal',
                N'Other'
            )
        ),
        CONSTRAINT CK_SKUMaster_Status CHECK (
            SKUStatus IN (N'In Development', N'Active', N'Discontinued', N'On Hold')
        ),
        CONSTRAINT CK_SKUMaster_ServingCount CHECK (
            ServingCount IS NULL OR ServingCount > 0
        ),
        CONSTRAINT CK_SKUMaster_COGS CHECK (
            COGS IS NULL OR COGS >= 0
        ),
        CONSTRAINT CK_SKUMaster_WholesalePrice CHECK (
            WholesalePrice IS NULL OR WholesalePrice >= 0
        ),
        CONSTRAINT CK_SKUMaster_MSRP CHECK (
            MSRP IS NULL OR MSRP >= 0
        )
    );

    CREATE NONCLUSTERED INDEX IX_SKUMaster_ProductName
        ON dbo.SKUMaster (ProductName);

    CREATE NONCLUSTERED INDEX IX_SKUMaster_Brand
        ON dbo.SKUMaster (Brand);

    CREATE NONCLUSTERED INDEX IX_SKUMaster_SKUStatus
        ON dbo.SKUMaster (SKUStatus);

    CREATE NONCLUSTERED INDEX IX_SKUMaster_GTIN14
        ON dbo.SKUMaster (GTIN14)
        WHERE GTIN14 IS NOT NULL;

    CREATE NONCLUSTERED INDEX IX_SKUMaster_UPC
        ON dbo.SKUMaster (UPC)
        WHERE UPC IS NOT NULL;
END;
GO
