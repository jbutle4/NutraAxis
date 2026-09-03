/*
  Provider signup — align AccsClinicId with AccsCompanyId.

  ACCS company admin custom attribute clinic_id is backfilled separately via:
    php scripts/repair-provider-signup-clinic-id.php
*/

UPDATE dbo.ProviderSignupApplication
SET AccsClinicId = CAST(AccsCompanyId AS NVARCHAR(50)),
    LastSavedAt = SYSUTCDATETIME()
WHERE AccsCompanyId IS NOT NULL
  AND (
      AccsClinicId IS NULL
      OR AccsClinicId <> CAST(AccsCompanyId AS NVARCHAR(50))
  );
