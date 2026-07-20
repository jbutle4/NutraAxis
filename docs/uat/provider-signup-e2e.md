# Provider Signup E2E UAT

End-to-end UAT for the practitioner application flow through ACCS company and user creation.

## Happy path covered

1. Start application (`/provider-signup/application.php` → `start.php`)
2. Acknowledge Practitioner Reseller Policy (`/provider-signup/policy.php`)
3. Save draft with company, admin, qualifications, ACH
4. Upload reseller certificate PDF
5. Ops login
6. Approve application
7. Create ACCS company (provision)
8. Assert `Status=Provisioned` + ACCS company/customer IDs
9. Write sign-off report under `artifacts/uat/`

## Prerequisites

- Target App Service reachable (default `https://nutraaxisweb.azurewebsites.net`)
- Ops user with **ProviderAccountReview** update permission
- Stage ACCS credentials configured on the app (`ADOBE_COMMERCE_*`, `PROVIDER_SIGNUP_ACCS_ENVIRONMENT=stage`)
- Azure blob + encryption keys for certificate upload
- Node.js 18+

## Run

```bash
cd /Users/jbutle4/Sites/nutraaxis

OPS_LOGIN='you@nutraaxislabs.com' \
OPS_PASSWORD='***' \
npm run e2e:provider-signup-uat
```

Stop before ACCS creation (draft + approve only):

```bash
OPS_LOGIN='...' OPS_PASSWORD='...' npm run e2e:provider-signup-uat -- --through=approve
```

Draft-only smoke (no ops credentials):

```bash
npm run e2e:provider-signup-uat -- --through=draft
```

## Environment variables

| Variable | Required | Default | Purpose |
| --- | --- | --- | --- |
| `OPS_LOGIN` | for approve+ | — | Ops dashboard email |
| `OPS_PASSWORD` | for approve+ | — | Ops dashboard password |
| `E2E_BASE_URL` / `BASE_URL` | no | Azure App Service URL | Target host |
| `E2E_PROVIDER_EMAIL` | no | unique `uat.provider+…@nutraaxislabs.com` | Application start email |
| `E2E_ADMIN_EMAIL` | no | unique admin email | ACCS customer email |
| `E2E_NPI_NUMBER` | no | `1679576722` | NPI used on form |
| `E2E_ACH_ROUTING` | no | `021000021` | 9-digit routing |
| `E2E_ACH_ACCOUNT` | no | `123456789` | Test account number |
| `E2E_EMAIL_DOMAIN` | no | `nutraaxislabs.com` | Domain for generated emails |

Values may also be loaded from repo `.env` if present.

## Sign-off artifacts

Each run writes:

- `artifacts/uat/provider-signup-e2e-<timestamp>.json`
- `artifacts/uat/provider-signup-e2e-<timestamp>.md`

The markdown report includes the automated checklist, ACCS IDs, links, and an approver signature table for UAT completion.

Manual remaining check after PASS: confirm the provider “Clinic Store is ready” email arrived for the admin address used in the run.
