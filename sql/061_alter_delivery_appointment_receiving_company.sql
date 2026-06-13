/*
  NutraAxis Operations — Delivery appointment receiving company contact fields
*/

IF COL_LENGTH('dbo.DeliveryAppointmentScheduling', 'ReceivingCompanyEmail') IS NULL
BEGIN
    ALTER TABLE dbo.DeliveryAppointmentScheduling
        ADD ReceivingCompanyEmail NVARCHAR(200) NULL;
END;
GO

IF COL_LENGTH('dbo.DeliveryAppointmentScheduling', 'ReceivingCompanyContact') IS NULL
BEGIN
    ALTER TABLE dbo.DeliveryAppointmentScheduling
        ADD ReceivingCompanyContact NVARCHAR(200) NULL;
END;
GO

IF COL_LENGTH('dbo.DeliveryAppointmentScheduling', 'ReceivingCompanyPhone') IS NULL
BEGIN
    ALTER TABLE dbo.DeliveryAppointmentScheduling
        ADD ReceivingCompanyPhone NVARCHAR(50) NULL;
END;
GO
