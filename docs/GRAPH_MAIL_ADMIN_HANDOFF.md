# Graph mail sender — Entra / Exchange admin handoff

Infrastructure provisioned in subscription resource group **NutraSync** (Contributor). Remaining steps require **Entra ID** and **Exchange Online** admin.

## Already created (do not recreate)

| Resource | Name / value |
|----------|----------------|
| Key Vault | `nutraaxis-mail-kv` (`https://nutraaxis-mail-kv.vault.azure.net/`) |
| Signing cert (KV) | `nutraaxis-mail-sender` (self-signed, CN=`nutraaxis-mail-sender`, expires ~2028-08-31, SHA1 `8D0970829781D76EC90861AFAD6812B09511DB4B`) |
| Service Bus queue | `sb-forecast-tool` / **`outbound-mail`** (DLQ on expire, max delivery 10) |
| Function MI (prod) | `Nutra-forecast-tool-prod` principal `ba34985f-4eef-4e95-8eff-8fb26b29b13a` |
| Function MI (non-prod) | `Nutra-forecast-tool` principal `ddcd6859-4ae6-4e71-a87b-f69e539aea44` |
| App Service MI | `nutraaxisweb` principal `ca35f2a7-82ed-4d6d-9f97-d00621c71290` |
| KV access | Access-policy model; both Function MIs + webapp MI have **Get/List** on certs + secrets |
| Tenant ID | `60392fb7-51ea-497a-8a08-0ec0265a97c7` (also set as `ENTRA_TENANT_ID` on apps) |
| From mailbox | `notifications@nutraaxislabs.com` (`GRAPH_MAIL_FROM`) |
| App settings | On `nutraaxisweb`, `Nutra-forecast-tool-prod`, `Nutra-forecast-tool`: `KEYVAULT_*`, `GRAPH_MAIL_FROM`, `SERVICEBUS_OUTBOUND_MAIL_QUEUE`, `MAIL_TRANSPORT=smtp` (until code cutover). `ENTRA_MAIL_CLIENT_ID` is **empty** pending app registration. |
| Public CER (local) | `.azure/nutraaxis-mail-sender.cer` (gitignored) — also downloadable from Key Vault |

`SERVICEBUS_CONNECTION_STRING` was copied to `nutraaxisweb` so PHP can enqueue later (same namespace as Functions).

---

## Admin checklist (Entra + Exchange)

### 1. App registration
1. Entra ID → App registrations → **New registration**
2. Name: `nutraaxis-mail-sender`
3. Supported account types: **Single tenant**
4. No redirect URI
5. Record **Application (client) ID**

### 2. API permissions
1. API permissions → Add → **Microsoft Graph** → **Application permissions** → `Mail.Send`
2. **Grant admin consent** for the tenant

### 3. Upload certificate
1. Certificates & secrets → Certificates → **Upload certificate**
2. Upload the public cert from Key Vault `nutraaxis-mail-sender`  
   - Portal: Key Vault → Certificates → Download in CER/PEM  
   - Or use local file `.azure/nutraaxis-mail-sender.cer` from an engineer workstation
3. Confirm thumbprint matches `8D0970829781D76EC90861AFAD6812B09511DB4B` (SHA1)

### 4. Exchange application access policy
Restrict the app so it can send **only** as `notifications@nutraaxislabs.com`:

```powershell
Connect-ExchangeOnline

New-DistributionGroup -Name "NutraAxis-Mail-Sender-Restriction" `
  -Type Security `
  -Members notifications@nutraaxislabs.com

# Use the app registration's client ID:
New-ApplicationAccessPolicy `
  -AppId "<ENTRA_MAIL_CLIENT_ID>" `
  -PolicyScopeGroupId "NutraAxis-Mail-Sender-Restriction" `
  -AccessRight RestrictAccess `
  -Description "Restrict nutraaxis-mail-sender Graph Mail.Send to notifications@ only"
```

(Adjust group creation if a suitable mail-enabled security group already exists.)

### 5. Return values to engineering
Set on **all three** apps (`nutraaxisweb`, `Nutra-forecast-tool-prod`, `Nutra-forecast-tool`):

| Setting | Value |
|---------|--------|
| `ENTRA_MAIL_CLIENT_ID` | Application (client) ID from step 1 |

Tenant ID is already set. No client secret is used; auth is certificate-based via Key Vault.

### 6. Optional verification (admin or engineering)
After client ID is set and Graph sender code is deployed:

```bash
# Expect success for notifications@; expect failure for any other mailbox
```

Use a Graph `sendMail` probe (Function diagnostic) as `notifications@nutraaxislabs.com`.

---

## Not admin — still engineering (Phases 2–5)

- Azure Function Service Bus trigger + MSAL cert + Graph `sendMail`
- PHP `includes/mail.php` enqueue path + feature flag
- Cut over Function nodemailer helpers
- Provider-signup E2E after cutover
- Flip `MAIL_TRANSPORT` from `smtp` to `servicebus` / `graph`
