/*
  NutraAxis Operations — Marketing employee email list

  Source: Marketing/Employee List/EmailList.xlsx (Consolidated sheet)
  Loaded: 2026-06-18
*/

IF OBJECT_ID(N'dbo.EmployeeList', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.EmployeeList (
        EmployeeListID      INT             NOT NULL IDENTITY(1,1),
        Company             NVARCHAR(50)    NULL,
        LastName            NVARCHAR(100)   NULL,
        FirstName           NVARCHAR(100)   NULL,
        Email               NVARCHAR(254)   NOT NULL,
        Department          NVARCHAR(100)   NULL,
        JobTitle            NVARCHAR(120)   NULL,
        Group1              NVARCHAR(50)    NULL,
        Group2              NVARCHAR(50)    NULL,
        FirstEmail          INT             NULL,
        ImportedAt          DATETIME2(0)    NOT NULL CONSTRAINT DF_EmployeeList_ImportedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_EmployeeList PRIMARY KEY CLUSTERED (EmployeeListID),
        CONSTRAINT UQ_EmployeeList_Email UNIQUE (Email)
    );

    CREATE NONCLUSTERED INDEX IX_EmployeeList_Company
        ON dbo.EmployeeList (Company, LastName, FirstName);

    CREATE NONCLUSTERED INDEX IX_EmployeeList_Group1
        ON dbo.EmployeeList (Group1)
        WHERE Group1 IS NOT NULL;
END;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.EmployeeList)
BEGIN

    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Flora', N'Jessica', N'jflora@wellsrx.com', N'Ocala SuperVIPII', N'Senior Customer Service Manager Super VIP and VIP2', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'James', N'Cody', N'cjames@wellsrx.com', N'Ocala SuperVIPII', N'Assistant Customer Service Manager', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Ally', N'Bebi', N'bally@wellsrx.com', N'Ocala SuperVIPII', N'Super VIP/VIPII CS3', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Flora', N'Jamie', N'jjflora@wellsrx.com', N'Ocala SuperVIPII', N'CS Supervisor for VIPII', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Mayberry', N'Danielle', N'dmayberry@wellsrx.com', N'Ocala SuperVIPII', N'Super VIP/VIPII CS2', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Munday', N'Erin', N'emunday@wellsrx.com', N'Ocala SuperVIPII', N'Blmd-CSR', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Ordonez', N'Amneris', N'aordonez@wellsrx.com', N'Ocala SuperVIPII', N'CS2 BLMD VIPII', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Miller', N'James', N'jmiller@wellsrx.com', N'Ocala SuperVIPII', N'Super VIP/VIPII CS3 ', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Rivera', N'Sofia', N'srivera@wellsrx.com', N'Ocala SuperVIPII', N'CS2 BLMD VIPII', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Melendez', N'Victoria', N'vmelendez@wellsrx.com', N'Ocala SuperVIPII', N'CS2 BLMD VIPII', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Mendez', N'Daniela', N'dmendez@wellsrx.com', N'Ocala SuperVIPII', N'CS2 BLMD VIPII', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Parodi', N'Andrea', N'aparodi@wellsrx.com', N'Ocala SuperVIPII', N'BLMD CS1', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Moreno Juarez', N'Han', N'hmoreno@wellsrx.com', N'Ocala SuperVIPII', N'Turnaround Time Specialist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Spivey', N'Gaye', N'gayehead@yahoo.com', N'Ocala SuperVIPII', N'CS2 BLMD VIPII', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Moore', N'Ciera', N'harden6000@yahoo.com', N'Ocala SuperVIPII', N'Super VIP/VIPII CS2', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Murray', N'Gregory', N'gmurray@wellsrx.com', N'Ocala SuperVIPII', N'Super VIP/VIPII CS2', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Rubio', N'Tina', N'trubio@wellsrx.com', N'OCALAADMIN          ', N'Account Receivable Specialist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Newman', N'Janice', N'jnewman@wellsrx.com', N'OCALAADMIN          ', N'Accounts Receivable-collections', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Bode', N'Paola', N'pbode@wellsrx.com', N'OCALAADMIN          ', N'Administrative/AR Supervisor', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Bode', N'Miriam', N'miriam_bode@hotmail.com', N'OCALAADMIN          ', N'Administrative Assistant', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Birth', N'Devin', N'devinbirth99@gmail.com', N'OCALAADMIN          ', N'Accounts Receivable Clerk', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Hernandez', N'Ronmery', N'ronmrryh@gmail.com', N'OCALAADMIN          ', N'Front Desk Receptionist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Brill', N'Jennifer', N'jbrill@wellsrx.com', N'OCALAANALYTIC NEW AC', N'Customer Service Trainer', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Lerner', N'Geneva', N'glerner@wellsrx.com', N'OCALAANALYTIC NEW AC', N'Training and Development Manager', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Thomas', N'Charity', N'cthomas@wellsrx.com', N'OCALAANALYTIC NEW AC', N'Customer Service Trainer', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Boyle', N'Cody', N'cboyle@wellsrx.com', N'OCALAANALYTICS      ', N'IT Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Herrero', N'Tatiana', N'therrero@wellsrx.com', N'OcalaCust Svc', N'Customer Service Representative', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Nelson', N'Susan', N'snelson@wellsrx.com', N'OcalaCust Svc', N'Customer Service Manager', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'French', N'Ashtin', N'afrench@wellsrx.com', N'OcalaCust Svc       ', N'Customer Service Representative', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Hall', N'Karen', N'kgonz75@gmail.com', N'OcalaCust Svc       ', N'Customer Service Representative (CS2)', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Hartley', N'Michelle', N'Mhartley@wellsrx.com', N'OCALACust Svc       ', N'Customer Service Representative', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Lewis', N'Shawna', N'slewis@wellsrx.com', N'OcalaCust Svc       ', N'Assistant Customer Service Manager', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Truelove', N'Catherine', N'ctruelove@wellsrx.com', N'OcalaCust Svc       ', N'Customer Service Representative', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Alvarez', N'Lizbeth', N'lalvarez@wellsrx.com', N'OCALACust Svc       ', N'Customer Service Representative (CS2)', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Harney', N'Kaylee', N'kharney@wellsrx.com', N'OCALACust Svc       ', N'Customer Service Representative', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Cooper', N'Alexis', N'ACOOPER@WELLSRX.COM', N'OCALACust Svc       ', N'CS1/Triage', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Espinoza', N'McKanzy', N'mespinoza@wellsrx.com', N'OCALACust Svc       ', N'CS1/Triage Lead', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Carroll', N'Destiny', N'dcarroll@wellsrx.com', N'OCALACust Svc       ', N'Customer Service Representative', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Dyer', N'Farrah', N'fdyer@wellsrx.com', N'OCALACust Svc       ', N'Customer Service Representative', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Randolph', N'Jennifer', N'jcobaugh@wellsrx.com', N'OCALACust Svc       ', N'Customer Service Representative (CS2)', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Freeze', N'Amethyst', N'afreeze@wellsrx.com', N'OCALACust Svc       ', N'Customer Service Representative (CS3)', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Wiltshire', N'Ashlee', N'awiltshire@wellsrx.com', N'OCALACust Svc       ', N'Customer Service Representative (CS2)', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Gonzalez', N'Chynna', N'cgonzalez@wellsrx.com', N'OCALACust Svc       ', N'CS1/Triage', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Ho', N'Nhat Hoa', N'jho@wellsrx.com', N'OCALACust Svc       ', N'Customer Service Representative (CS2)', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Palmieri', N'Emily', N'emilypalmieri01@gmail.com', N'OCALACust Svc       ', N'CS2 Lead', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Gibson', N'Joni', N'jgibson@wellsrx.com', N'OCALACust Svc       ', N'Customer Service Representative (CS2)', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Araujo Sulbaran', N'Patricia', N'araujosulbaranpatricia@gmail.com', N'OCALACust Svc       ', N'Turnaround Time Ninja', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Chianti', N'Cecily', N'mamanose1107@gmail.com', N'OCALACust Svc       ', N'Customer Service Representative (CS3)', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Rawls', N'Gemani', N'grawls@wellsrx.com', N'OCALACust Svc       ', N'CS1/Triage', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Gonzalez', N'Crystal', N'cmgonzalez@wellsrx.com', N'OCALACust Svc       ', N'CS1/Triage', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Acosta', N'Tiana', N'tianamacosta14@gmail.com', N'OCALACust Svc       ', N'CS1/Triage', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Araujo Sulbaran', N'Francis', N'francisro782@gmail.com', N'OCALACust Svc       ', N'Turnaround Time Ninja', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Bushyager', N'Richard', N'richard@ioi-controls.com', N'OCALAFacilities ', N'Chief Engineer', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Brooks', N'Ryan', N'rbrooks@wellsrx.com', N'OCALAINVENTORY      ', N'Swat (finished Goods)', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Rodriguez', N'Georgiana', N'grodriguez@wellsrx.com', N'OCALANon Sterile Lab', N'Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Gill', N'Ashley', N'agill@wellsrx.com', N'OCALANon Sterile Lab', N'Non Sterile Supervisor', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Colon-Rodriguez', N'Leilany', N'lcolon@wellsrx.com', N'OCALANon Sterile Lab', N'Non Sterile Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Santillan', N'Jacqueline', N'jacquesanti099@gmail.com', N'OCALANon Sterile Lab', N'Non Sterile Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Cicalese', N'Nicholas', N'ncicalese@wellsrx.com', N'OCALANon Sterile Lab', N'Non Sterile Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Glucksman', N'Miranda', N'mirag631@gmail.com', N'OCALANon Sterile Lab', N'Non Sterile Lead -Night shift ', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Santiago Curbelo', N'Illeanys', N'coco.vmop@gmail.com', N'OCALANon Sterile Lab', N'Non Sterile Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Burgos Perez', N'Tayra', N'elysseburgos@gmail.com', N'OCALANon Sterile Lab', N'Non Sterile Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'McDuffie', N'Shaterra', N'terra.renae@gmail.com', N'OCALANon Sterile Lab', N'Non Sterile Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'McDuffie', N'Shatoya', N'toya.donta@gmail.com', N'OCALANon Sterile Lab', N'Non Sterile Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Williams', N'Robin', N'sharynewilliams22@yahoo.com', N'OCALANon Sterile Lab', N'Non Sterile Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Wilson', N'Lillie', N'lilliew1224@gmail.com', N'OCALANon Sterile Lab', N'Non Sterile Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Aysheh', N'Abed', N'aaysheh@wellsrx.com', N'OCALAPAR            ', N'Inventory & Par Manager', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Barrett', N'Noah', N'nbarrett@wellsrx.com', N'OCALAPAR            ', N'Par Room Lead', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Davis', N'Sally', N'sdavis@wellsrx.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Freitas', N'Anibal', N'afreitas@wellsrx.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Harden', N'Weston', N'wharden@wellsrx.com', N'OCALAPAR            ', N'Par Inventory Lead', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Fuertes Rivera', N'Chris', N'cfuertes@wellsrx.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Gonzalez', N'Alondra', N'alondragonzalez0305@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Simmons', N'David', N'dsimmons@wellsrx.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Jackson', N'Charvelle', N'cjklops4@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Garcia', N'Ricardo', N'ricardog3999@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Bundschuh', N'Faith', N'faithleeannbundschuh@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Stevens', N'Kendal', N'pstevens1031@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Askins', N'Karol', N'karolaskins03@yahoo.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Pineda', N'Angie', N'angiepinedas15@hotmail.com', N'OCALAPAR            ', N'Par Level Inventory Tech', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Brussot', N'Shania', N'shania.brussot03@icloud.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Desiderio-Ramirez', N'Rebecca', N'rebecca.ramirezzz05@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Moncada', N'Abbi', N'abbi.moncada68@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Boulas', N'Brianca', N'jg1dg2bb3@yahoo.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Fuertes Rivera', N'Jonathan', N'louisfuertes9@gmail.com', N'OCALAPAR            ', N'Par Level Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Elvy', N'Ikimbea', N'ikimbeaelvy@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Torres Rodriguez', N'Fany', N'fanyetorres@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Willis', N'Cai', N'caiwillis06@icloud.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Laconte', N'Aiden', N'aidenlaconte@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Curl', N'Kayla', N'kaylacurl29@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Gonzalez', N'Eddy', N'gonzalezeddy03@yahoo.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Melo-Gonzalez', N'Yuridia', N'yurimelog0615@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Miller', N'Syndey', N'sydneyallyse33@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Wheeler', N'Fallon', N'fallonwheeler2@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Proctor', N'Malynn', N'malynn.proctor112@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Scott', N'Heather', N'ralsha.girl@yahoo.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Boucard', N'Reginald', N'reginaldboucard@yahoo.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Sanz', N'Natalia', N'natysanz_24@hotmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Weber', N'Mark', N'markweber117@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Guzman', N'Ethan', N'ethan.jayg16@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Del Rio Sepulveda', N'Sebastian', N'ssepulveda@wellsrx.com', N'OCALAPAR            ', N'Receiving/Procurement Specialist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Brown', N'Isabella', N'isabellarae9@yahoo.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Gonzalez', N'Miguel', N'migonzzalezz@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Cassidy', N'Jamie', N'jamie.c.cassidy@gmail.com', N'OCALAPAR            ', N'Par Inventory Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Brown', N'Howard', N'hbrown@wellsrx.com', N'OcalaPharmacy', N'Pharmacist In Charge', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Drobiazgiewicz', N'Rhonda', N'rdrobiazgiewicz@wellsrx.com', N'OcalaPharmacy', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Ulbricht', N'Christopher', N'culbricht@wellsrx.com', N'OcalaPharmacy', N'V.p Of Operations', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Branch', N'Dina', N'dbranch@wellsrx.com', N'OcalaPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Campbell', N'Anthony', N'acampbell@wellsrx.com', N'OcalaPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Mallory', N'Marcus', N'mmallory@wellsrx.com', N'OcalaPharmacy       ', N'Assistant Pharmacist Manager ', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Pettengill', N'Kenneth', N'kpettengill@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Campbell', N'Harmony', N'hsanfilippo@wellsrx.com', N'OCALAPharmacy       ', N'Director of Pharmacy Production', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Woodruff', N'Craig', N'cwoodruff@wellsrx.com', N'OcalaPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Good', N'Ana', N'agood@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Rembert', N'George', N'grembert@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Case', N'Margie', N'mcase@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Gulick', N'Madison', N'mgulick@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Turnipseed', N'Lisa', N'Lturnipseed@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Mutschler', N'Jason', N'JMutschler@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Russell', N'David Anthony', N'drussell@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Le', N'Raymond', N'rle@wellsrx.com', N'OCALAPharmacy       ', N'Lead Pharmacist/Trainer 2nd shift', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Marsh', N'Roger', N'rmarsh@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Saunders Jr.', N'Phillip', N'psaunders@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Board', N'Andrew', N'aboard@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'McLean Collman', N'Shawn', N'smcollman@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Woodruff', N'Gisela', N'gwoodruff@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Fuller', N'Christopher', N'cfuller@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Getahun', N'Rahel', N'rgetahun@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Reilly', N'Jaclyn', N'jreilly@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Bergin', N'Jonathan', N'jibergin.rx@gmail.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Hassen', N'Wallelign', N'epistemobias@gmail.com', N'OCALAPharmacy       ', N'Pharmacist Intern', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Redden', N'Marcilyn', N'mredden@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Reyes', N'Joshua', N'joreyes@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Borchard', N'Robert', N'rborchard@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Sou', N'Sodang', N'ssou@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Mears', N'Terri', N'tsav62@gmail.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Ezepue', N'Julius', N'jezepue@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Rosenblatt', N'Michael', N'mrosenblatt@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Wise', N'Kenneth', N'KWise@wellsrx.com', N'OCALAPharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Hernandez Alonzo', N'Miguel', N'mhalonzo@wellsrx.com', N'OCALAQ/A Q/C         ', N'Stability Study Manager', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Garcia', N'Carlos', N'cgarcia@wellsrx.com', N'OCALAQ/A Q/C         ', N'Quality Control Supervisor', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Moore', N'Nichole', N'nmoore@wellsrx.com', N'OCALAQ/A Q/C         ', N'Quality Assurance Supervisor', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Oshrieh', N'Matthew', N'moshrieh88@yahoo.com', N'OCALAQ/A Q/C         ', N'Quality Assurance Lead', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Payne', N'Jennifer', N'penniferjayne@gmail.com', N'OCALAQ/A Q/C         ', N'Senior Microbiologist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Stefko', N'Melissa', N'Melissastefko@gmail.com', N'OCALAQ/A Q/C         ', N'Sr. VP of Compliance', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Thron', N'Sarah', N'sarahthron@yahoo.com', N'OCALAQ/A Q/C         ', N'Sr. Director of Regulatory', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Wade', N'Caitlin', N'cwade@wellsrx.com', N'OCALAQ/A Q/C         ', N'Document Control Specialist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Clark', N'Sydney', N'sclark@wellsrx.com', N'OCALAQ/A Q/C         ', N'Quality Control Microbiologist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Vargas-Ramirez', N'Nayeli', N'Nayeli0513@icloud.com', N'OCALAQ/A Q/C         ', N'Quality Control Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Patronski', N'Aubrey', N'apatronski@wellsrx.com', N'OCALAQ/A Q/C         ', N'Quality Control Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Casesa', N'Jennifer', N'jcasesa@wellsrx.com', N'OCALAQ/A Q/C         ', N'Executive Director of Quality Assurance', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Spang', N'Nicholas', N'nspang@wellsrx.com', N'OCALAQ/A Q/C         ', N'Sr. Investigation Specialist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Chisolm', N'Grace', N'gchisolm@wellsrx.com', N'OCALAQ/A Q/C         ', N'Document Control Specialist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Berthiaume', N'Brett', N'bberthiaume@wellsrx.com', N'OCALAQ/A Q/C         ', N'Equipment Program Coordinator', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Razo', N'Gustavo', N'grazo@wellsrx.com', N'OCALAQ/A Q/C         ', N'Investigation Specialist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Jaconis', N'Adam', N'ajaconis@gmail.com', N'OCALAQ/A Q/C         ', N'QA Project Manager', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Ferko', N'Brittney', N'Bferko@wellsrx.com', N'OCALAQ/A Q/C         ', N'Regulatory Affairs Associate', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Boquin Zamora', N'Romel', N'lifeluz@icloud.com', N'OCALAQ/A Q/C         ', N'Quality Control Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Hutchison', N'Gina', N'ghutchinson@wellsrx.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Lopez-Benitez', N'Avid', N'avid.benitez3303@gmail.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Flora II', N'Anthony', N'aflora@wellsrx.com', N'OCALASHIPPING       ', N'Shipping Lead', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Lukas', N'Carol', N'clukas@wellsrx.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Neumann', N'Erich', N'eneumann@wellsrx.com', N'OCALASHIPPING       ', N'Shipping Manager', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Urias Tovar', N'Yoselin', N'yoselinuriastovar01@gmail.com', N'OCALASHIPPING       ', N'Telehealth Lead- AM shift', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Urias Tovar', N'Azucena', N'aurias-tovar@wellsrx.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Santiago', N'Eddie', N'eddieus1980@gmail.com', N'OCALASHIPPING       ', N'Shipping Lead', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Urias Tovar', N'Elizabeth', N'eurias929@gmail.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Ventura', N'Nelly', N'nellyventura1231@gmail.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Vazquez', N'Luis', N'bobbylv2020@gmail.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Wray', N'Zachary', N'zwray@wellsrx.com', N'OCALASHIPPING       ', N'Receiving/Procurement Specialist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Urias Tovar', N'Ma', N'mtovar@wellsrx.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Sweat', N'Nathan', N'nsweat@wellsrx.com', N'OCALASHIPPING       ', N'Shipping Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Pulido', N'Samantha', N'sammi_pulido5@icloud.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Ventura', N'Kevin', N'kevinventura1223@gmail.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Colon', N'Nicholas', N'ntc96@outlook.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Rivera', N'Jacob', N'jacobrivera0507@yahoo.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Ramirez', N'Evelyn', N'evelyn.ramirez071@gmail.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Gonzalez Dieguez', N'Yunier', N'gonzalezyunier687@yahoo.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Pierce', N'Sierra', N'sierrapierce26@hotmail.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Pierce', N'Fredna', N'fredna.pierce@yahoo.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Cochran', N'Braiden', N'braidenjcochran3@gmail.com', N'OCALASHIPPING       ', N'Logistics technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Kern', N'Tyler', N'tkern@wellsrx.com', N'OCALASterile Lab    ', N'Sterile Compounding Manager (503A)', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Buehler', N'Jarett', N'jbuehler@wellsrx.com', N'OCALASterile Lab    ', N'Sterile Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Castelan', N'Moises', N'mcastelan@wellsrx.com', N'OCALASterile Lab    ', N'Sterile Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Landers', N'Hayley', N'Hayley.Landers@wellsrx.com', N'OCALASterile Lab    ', N'Sterile Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Parsons', N'Gabriel', N'gparsons@wellsrx.com', N'OCALASterile Lab    ', N'Sterile Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Sentz', N'Christopher', N'csentz@wellsrx.com', N'OCALASterile Lab    ', N'Sterile Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Gonzalez', N'Dayana', N'dgonzalez@wellsrx.com', N'OCALASterile Lab    ', N'Sterile Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Franco', N'Jeishalis', N'jfranco@wellsrx.com', N'OCALASterile Lab    ', N'Sterile Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Reyes', N'Johanny', N'jreyes@wellsrx.com', N'OCALASterile Lab    ', N'Sterile Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Correa', N'Kristopher', N'kcorrea88@yahoo.com', N'OCALASterile Lab    ', N'Sterile Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Alvarez Torres', N'Heily', N'heilyalvarez02@gmail.com', N'OCALASterile Lab    ', N'Sterile Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Gonzalez-Vale', N'Suanitsy', N'suanitsy@gmail.com', N'OCALASterile Lab    ', N'Non Sterile Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Vales Torres', N'Suanett', N'svales@wellsrx.com', N'OCALASterile Lab    ', N'Sterile Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Werfel', N'Jesse', N'jessewerfel@gmail.com', N'TN   CORP           ', N'Director of 503B Outsourcing Facility - TN', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Butler', N'Hanna', N'hbutler@wellsrx.com', N'TN   Non Sterile Lab', N'Pharmacy Technician Lead', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Bennett', N'Ikia', N'inance@wellsrx.com', N'TN   Non Sterile Lab', N'Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Ferguson', N'Shanicqua', N'neka_2010@hotmail.com', N'TN   Non Sterile Lab', N'Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Brandon', N'Maddie', N'brandonmaddie147@gmail.com', N'TN   Non Sterile Lab', N'Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Stover', N'Alexandria', N'astover@wellsrx.com', N'TN   Non Sterile Lab', N'Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Chute', N'Mallory', N'mchute@wellsrx.com', N'TN   Non Sterile Lab', N'Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Collier-Siebert', N'Rylie', N'rcollier-siebert@wellsrx.com', N'TN   Non Sterile Lab', N'Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'McVey', N'Erika', N'emcvey@wellsrx.com', N'TN   Non Sterile Lab', N'Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Jernigan', N'Tracy', N'tjernigan@wellsrx.com', N'TN   Pharmacy', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Beard', N'Sharon', N'sbeard@wellsrx.com', N'TN   Pharmacy       ', N'Lab Manager', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Santaniello', N'Nicholas', N'nsantaniello@wellsrx.com', N'TN   Pharmacy       ', N'Pharmacist In Charge', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Maynard', N'Austin', N'aj.maynard2@gmail.com', N'TN   Pharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Olusanya', N'Ayodeji', N'aolusanya@wellsrx.com', N'TN   Pharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Richard', N'Joshua', N'joshrichard15@gmail.com', N'TN   Pharmacy       ', N'Lab Manager', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Wright', N'Mary', N'mwright@wellsrx.com', N'TN   Pharmacy       ', N'Staff Pharmacist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Coleman', N'Kelsey', N'Kcoleman@wellsrx.com', N'TN   Q/A            ', N'Quality Assurance Manager', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Antunez', N'Rafael', N'RAntunez@wellsrx.com', N'TN   Q/A            ', N'QC Manager', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Poss', N'Jennifer', N'emmettposs@gmail.com', N'TN   Q/A            ', N'QC Environmental Monitoring Technician I', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Murray', N'Sarah', N'smurray@wellsrx.com', N'TN   Q/A            ', N'Sr. QC Tech', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Mann', N'Amanda', N'amann@wellsrx.com', N'TN   Q/A            ', N'Quality Assurance Associate', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Via', N'Samuel', N'dvia@wellsrx.com', N'TN   Q/A            ', N'QC Environmental Monitoring Technician I', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Yisrael', N'Tonyah', N'tyisrael@wellsrx.com', N'TN   Q/A            ', N'Quality Assurance Specialist I', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Frealy', N'Rachel', N'rfrealy@wellsrx.com', N'TN   Q/A            ', N'Quality Assurance Specialist I', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Myles', N'Rickie', N'rmyles@wellsrx.com', N'TN   Q/A            ', N'Director of Quality 503B Outsourcing Facility - Dyersburg TN', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Hatcher', N'Michelle', N'MHatcher@wellsrx.com', N'TN   Sterile Lab    ', N'Non Sterile Technician Supervisor', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Cobo', N'Fanny', N'fcobo@wellsrx.com', N'WELL ADMIN          ', N'HR Coordinator ', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Cooper', N'Summer', N'scooper@wellsrx.com', N'WELL ADMIN          ', N'HR Generalist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Sanchez Vargas', N'Valerie', N'vchang@wellsrx.com', N'WELL ANALYTIC NEW AC', N'New Accounts and Onboarding Manager', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Keene', N'Jacalyn', N'jkeene@wellsrx.com', N'WELL ANALYTIC NEW AC', N'New account and Verification Specialist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Munger', N'Lauren', N'lmunger@wellsrx.com', N'WELL ANALYTIC NEW AC', N'New account and Verification Specialist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Holek', N'Olivia', N'oholek@wellsrx.com', N'WELL ANALYTIC NEW AC', N'New account and Verification Specialist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Munger', N'Johnathon', N'JMunger@wellsrx.com', N'WELL ANALYTICS      ', N'CITO', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Serrano', N'Alexandra', N'aserrano@wellsrx.com', N'WELL ANALYTICS PRIC ', N'Sr. Account Manager of Analytics', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Fishman', N'Kristopher', N'kfishman@wellsrx.com', N'Well CORP', N'President', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Garvey ', N'Veronica', N'vgarvey@wellsrx.com', N'WELL CORP           ', N'Human Resources Director', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Bowe', N'Natalie', N'nbowe@wellsrx.com', N'WELL CORP           ', N'Chief Marketing & National Accounts Officer', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Huynh', N'Eric', N'ehuynh@wellsrx.com', N'WELL CORP           ', N'Chief Revenue Officer', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Stoneking', N'Joshua', N'jstoneking@wellsrx.com', N'WELL CORP           ', N'Senior Vice President', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Tejada', N'Precious', N'ptejada@wellsrx.com', N'WELL Cust Svc       ', N'Lead Telehealth Coordinator', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Blackthorn', N'Aubrey', N'226chris@gmail.com', N'WELL Cust Svc       ', N'Intake Specialist (CS2)', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Rush', N'Kizzie', N'krush@wellsrx.com', N'WELL Cust Svc       ', N'Intake Specialist (CS2)', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Rissmiller', N'Jena', N'kaj3689447@gmail.com', N'WELL Cust Svc       ', N'Customer Service Representative (CS2)', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Weber', N'Alyssa', N'akweber86@yahoo.com', N'WELL Cust Svc       ', N'Customer Service Representative (CS2)', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Landa', N'Dianelys', N'dlanda@wellsrx.com', N'WELL Cust Svc       ', N'Intake Specialist (CS2)', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Massey', N'James', N'jmassey@nfcllc.com', N'WELL IT             ', N'Director of IT', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Goodrich', N'Brian', N'bgoodrich@wellsrx.com', N'Well Sales          ', N'Business Manager', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Ho Shue', N'Richard', N'RHoShue@wellsrx.com', N'WELL SALES          ', N'Inside Sales Representative', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Macintosh', N'John', N'jmacintosh@wellsrx.com', N'WELL SALES          ', N'Director of Training, Sales Enablement', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Parkes', N'Austin', N'aparkes@wellsrx.com', N'WELL SALES          ', N'Inside Sales Representative', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Baklor', N'Kendall', N'kbaklor@wellsrx.com', N'WELL SALES          ', N'Outside Sales rep', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'McKeever', N'Kristina', N'kmckeever@wellsrx.com', N'WELL SALES          ', N'Sales Account Manager', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Swinger', N'Logan', N'logaswin5@gmail.com', N'WELL SALES          ', N'Territory Manager', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'McLelland', N'Jordan', N'jordanm40@gmail.com', N'WELL SALES          ', N'Territory Manager', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Gilmour', N'Jessica', N'jessicacjones9@gmail.com', N'WELL SALES          ', N'Territory Manager', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Southall', N'John', N'jsouthall@wellsrx.com', N'WELL SALES          ', N'Territory Manager', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Hite', N'Joezette', N'jhite@wellsrx.com', N'WELL SALES          ', N'Territory Manager', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Botts', N'Courtney', N'cbotts@wellsrx.com', N'WELL SALES          ', N'Territory Manager', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Ferrell', N'Jonathan', N'jferrell@wellsrx.com', N'WELL SALES          ', N'Regional Vice President of Sales', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Clemens', N'Paul', N'pclemens@wellsrx.com', N'WELL SALES          ', N'Regional Vice President of Sales', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPN', N'Downey', N'Konnor', N'kdowney@wellsrx.com', N'Well Sales          ', N'Business Development Manager', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Rozell', N'Martha', N'mrozell@wppharmalabs.com', N'Admin', N'Finance and Executive Support Specialist', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Myers', N'Kelsey', N'amyers@wppharmalabs.com', N'Admin', N'Regulatory Affairs and Production Liaison', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Crociata', N'Gianna', N'gmcrociata@gmail.com', N'Admin', N'Training and Program Manager', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Weathers', N'Jayquan', N'jayquanm99@gmail.com', N'Admin', N'Systems Adminstrator I', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Vincent', N'Danniella', N'dvincent@wppharmalabs.com', N'Admin', N'New Accounts Specialist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Lathrop', N'Christina', N'christina.lathrop314@gmail.com', N'Admin', N'New Account & Pricing Specialist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Flora', N'Michael', N'mflora@wppharmalabs.com', N'Corporate', N'VP of Operations', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Juarez', N'Amparo', N'amberjuarez69@yahoo.com', N'Customer Serv', N'Customer Service Manager', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Boudreaux', N'Carmen', N'cboudreaux@wppharmalabs.com', N'Customer Serv', N'Customer Service, Sr Lead', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Smith', N'Faustina', N'love4a22@yahoo.com', N'Customer Serv', N'Customer Service rep CS2', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Aquino', N'Romelyn', N'romeuyu@gmail.com', N'Customer Serv', N'Customer Service rep CS2', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Khwaja', N'Raisa', N'raisakhwaja1@gmail.com', N'Customer Serv', N'Customer Service Representative - Order Delay', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Salinas', N'Kayla', N'kaysalinas@wppharmalabs.com', N'Customer Serv', N'Customer Service -Oder Delay', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Templeton', N'Courtney', N'ctempleton@wppharmalabs.com', N'Customer Serv', N'Customer Service rep CS2', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Juarez', N'Brianna', N'briannajuarez601@gmail.com', N'Customer Serv', N'Customer Service Rep-CS3', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Doan', N'Katelyn', N'doankatelyn03@gmail.com', N'Customer Serv', N'Customer Service rep CS2', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Flores', N'Juana', N'jflores@wppharmalabs.com', N'Non Sterile Lab', N'Non Sterile Lab Lead', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Loya', N'Melanie', N'mloya@wppharmalabs.com', N'Non Sterile Lab', N'Non-Sterile Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Marquez Ortiz', N'Samantha', N'smarquez@wppharmalabs.com', N'Par Room', N'PAR Room Supervisor', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Servin', N'Brayson', N'brayson845@yahoo.com', N'Par Room', N'Par Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Uribe', N'Veronica', N'vuribe@wppharmalabs.com', N'Par Room', N'Par Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Boyd', N'Spencer', N'spencer.boyd9001@gmail.com', N'Par Room', N'Par Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Ramirez', N'Raul', N'raulraulr713@gmail.com', N'Par Room', N'Par Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Robles', N'Cinthya', N'roblescinthya02@gmail.com', N'Par Room', N'Par Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Lopez', N'Erica', N'ejlopez1992@gmail.com', N'Par Room', N'Par Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Randle', N'Jonathan', N'jrandle@wppharmalabs.com', N'Par Room', N'Par Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Hiddessen', N'Kim', N'khiddessen@wppharmalabs.com', N'Pharmacy', N'Pharmacist in Charge', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'May', N'Jerry', N'jmay@wppharmalabs.com', N'Pharmacy', N'Assistant Pharmacist Manager', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Paul', N'Kayra', N'kayrapaul@yahoo.com', N'Pharmacy', N'Staff Pharmacist', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Le', N'Lien', N'lien5_2@yahoo.com', N'Pharmacy', N'Sterile Lab Supervisor', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Duong', N'Tunhi', N'nickyduongg@gmail.com', N'Pharmacy', N'Staff Pharmacist', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Ginn', N'Joseph', N'jginn@wppharmalabs.com', N'Pharmacy', N'Staff Pharmacist', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Nguyen', N'Karen', N'knguyen@wppharmalabs.com', N'Pharmacy', N'Staff Pharmacist', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Le', N'Giao', N'gqle0710@yahoo.com', N'Pharmacy', N'Staff Pharmacist', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Rust', N'Bruce', N'buemgm@gmail.com', N'Pharmacy', N'Sterile Pharmacist', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Collett', N'Jacob', N'jakemcollett@gmail.com', N'Pharmacy', N'Director of Pharmacy Production', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Hua', N'Helen', N'hhua@wppharmalabs.com', N'Pharmacy', N'Staff Pharmacist', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Phu', N'Edward', N'edlphu@gmail.com', N'Pharmacy', N'Sterile Staff Pharmacist', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Varghese', N'Christina', N'Christinatv18@gmail.com', N'Pharmacy', N'Staff Pharmacist', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Tesfaye', N'Ruth', N'rtesfaye@wppharmalabs.com', N'Pharmacy', N'Staff Pharmacist', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Watkins', N'Matthew', N'mwatkins@wppharmalabs.com', N'Q/A-Q/C', N'Quality Assurance Supervisor', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Muentes', N'Ruth', N'ruthie.cpht@gmail.com', N'Q/A-Q/C', N'Document Control', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Long', N'Miranda', N'mmlong27@gmail.com', N'Q/A-Q/C', N'Quality Control Lead', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Hoag', N'James', N'jazzhoag@gmail.com', N'Q/A-Q/C', N'Quality Control Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Villasenor Gonzalez', N'Xena', N'xenavillasenor@yahoo.com', N'Q/A-Q/C', N'Quality Control Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Cordova Moreno', N'Rosa', N'cordovamoreno.rosa@gmail.com', N'Q/A-Q/C', N'Quality Control Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Vuong', N'Ken', N'kvuong@wppharmalabs.com', N'Q/A-Q/C', N'Quality Control Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Gonzalez', N'Veronica', N'vgonzalez@wppharmalabs.com', N'Q/A-Q/C', N'Quality Control Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Miranda', N'Stacy', N'stacy.miranda24@gmail.com', N'Q/A-Q/C', N'Quality Assurance Associate', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Davis', N'Alexandria', N'ldavis@wppharmalabs.com', N'Sales', N'Inside Sales Representative', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Salas', N'Jonathon', N'jonathonesalas@gmail.com', N'Ship', N'Shipping & Receiving / Procurement Specialist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Marquez', N'Nicholas', N'nicomarquez534@gmail.com', N'Ship', N'Shipping Technician Lead', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Cantu', N'Timothy', N'timmy.cantu236@gmail.com', N'Ship', N'Shipping Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Marquez', N'Jordan', N'jmarquez@wppharmalabs.com', N'Ship', N'Shipping Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Hunter', N'Brandon', N'bhunter@wppharmalabs.com', N'Ship', N'Shipping Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Camacho', N'Jesse', N'jcamacho@wppharmalabs.com', N'Ship', N'Shipping Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Gonzalez', N'Gabrielle', N'ggonzalez@wppharmalabs.com', N'Ship', N'Shipping Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Escobar', N'Juan', N'Jescobar@wppharmalabs.com', N'Ship', N'Shipping Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Lopez', N'Juan', N'juandlopez2020@gmail.com', N'Ship', N'Shipping Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Killough', N'Ryan', N'ryanmatkillough@gmail.com', N'Ship', N'Shipping Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Marquez', N'Nasaria', N'nasmarquez@wppharmalabs.com', N'Ship', N'Shipping Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Jerdee', N'Evie', N'ejerdee@wppharmalabs.com', N'Sterile Lab', N'Sterile tech/Pre production', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Munguia', N'Maribel', N'maribelmunguia@ymail.com', N'Sterile Lab', N'Lead Sterile Pharmacy Technician ', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Robledo', N'Yessenia', N'yrobledo@wppharmalabs.com', N'Sterile Lab', N'Sterile Pre Production Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Moreno Tiburcio', N'Karen', N'kmoreno243@gmail.com', N'Sterile Lab', N'Sterile Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Mejia', N'Citlali', N'cmejia@wppharmalabs.com', N'Sterile Lab', N'Sterile Pre/Post Production Support Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'WPPL', N'Olivo Zarate', N'Alejandra', N'alley121314@gmail.com', N'Sterile Lab', N'Sterile Pharmacy Technician', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Melbourne ', N'Sweeney', N'Scott', N'slsmedsales@gmail.com', N'Corporate', N'Territory Sales Manager - Central Florida', N'employee', N'sales', 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Melbourne ', N'Caleb', N'Candice', N'ccaleb@melbournepharma.com', N'Customer Service', N'Customer Service Rep CS2', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Melbourne ', N'Sawyer', N'Christina', N'csawyer@melbournepharma.com', N'Customer Service', N'Customer Service Rep CS3', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Melbourne ', N'Thompson', N'Bryanna', N'bthompson@melbournepharma.com', N'Customer Service', N'Customer Service Rep CS3', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Melbourne ', N'Robinson', N'Robert', N'rrobinson@melbournepharma.com', N'Non-Sterile Lab', N'Non Sterile Lab Manager', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Melbourne ', N'Torres', N'Connie', N'ctorres@melbournepharma.com', N'Operations', N'Ops Manager', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Melbourne ', N'Thipphayoth', N'Rochelle', N'rthipphayoth@melbournepharma.com', N'PAR', N'PAR/Shipping Manager', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Melbourne ', N'Boehmer', N'Michael', N'mboehmer@melbournepharma.com', N'Pharmacy', N'President/PIC', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Melbourne ', N'Brierton', N'Scott', N'scott.brierton@gmail.com', N'Pharmacy', N'Staff Pharmacist', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Melbourne ', N'Azizi', N'Jamal', N'jazjan2019@gmail.com', N'QA/QC', N'Director of Quality Systems, Chemical Compliance', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Melbourne ', N'Sampadian', N'Jeffery', N'jsampadian@melbournepharma.com', N'Shipping', N'Distribution Clerk', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Melbourne ', N'Hartje', N'Frederick', N'fhartje@melbournepharma.com', N'Sterile Lab', N'Sterile Lab Manager', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'FHM', N'Haines', N'Kathryn', N'KHaines@factorhealthmarketing.com', N'Marketing', N'Sr Director of Marketing/President ', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'FHM', N'Oropeza Arrechedera', N'Yosbel', N'yoropeza@factorhealthmarketing.com', N'Marketing', N'Senior Graphic Designer', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'FHM', N'George', N'Abigail', N'ageorge@factorhealthmarketing.com', N'Marketing', N'Marketing Coordinator', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'FHM', N'Perez Wait', N'Sabrina', N'sperez@factorhealthmarketing.com', N'Marketing', N'Graphic Designer', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'FHM', N'Moss', N'Amanda', N'mmoss@factorhealthmarketing.com', N'Marketing', N'Assistant Director of Marketing', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'FHM', N'Luisi', N'Alexandra', N'aluisi@factorhealthmarketing.com', N'Marketing', N'Marketing Coordinator', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'FMS', N'Beerbower', N'Bradley', N'bbeerbower@factormedicalsupply.com', N'Supply', N'Certified Designated Representative', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'FMS', N'Watson', N'Tate', N'twatson@factormedicalsupply.com', N'Supply', N'Certified Designated Representative', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'FMS', N'Thompson', N'Mary', N'mthompson@factormedicalsupply.com', N'Supply', N'President', N'employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'FMS', N'Santiago', N'Steven', N'ssantiago@factormedicalsupply.com ', N'Supply', N'Procurement Specialist', NULL, NULL, NULL);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Summit', N'Markt', N'Christian', N'cmarkt@summitpharmacyrx.com', N'Pharmacy', N'Vice President/Pharmacist in Charge', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Summit', N'Roqueza', N'Chelsey', N'croqueza@summitpharmacyrx.com ', N'Pharmacy', N'Lead Pharmacy Tecnician', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Summit', N'Galusha', N'Shirley', N'sgalusha@summitpharmacyrx.com', N'Pharmacy', N'Pharmacy Technician', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'Summit', N'Bergquist', N'Debra', N'dbergquist@summitpharmacyrx.com', N'Ship', N'Pharmacy Clerk/Shipping', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'NFC', N'Weber', N'Devon', N'dweber@nfcllc.com ', N'BI & Reporting', N'Busienss Analyst', N'Employee', NULL, 1);
    INSERT INTO dbo.EmployeeList (Company, LastName, FirstName, Email, Department, JobTitle, Group1, Group2, FirstEmail)
    VALUES (N'NFC', N'Richmond', N'Jennifer', N'jrichmond@nfcllc.com', N'Sr. Manager Programs and Projects', N'OK', N'Employee', NULL, 1);
END;
GO
