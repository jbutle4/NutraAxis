# Azure Functions — nutra-forecast-tool

Timer and HTTP functions for NutraAxis scheduled jobs.

## Functions

| Function | Type | Purpose |
|----------|------|---------|
| `ping` | HTTP | Health check; used by PHP `/function-test/` |
| `forecast-plan` | Timer | Sunday 1:30 AM Central — calls PHP `/cron/weekly-demand.php` |

## Local development

### 1. Settings

Copy the example and add your cron secret (same value as PHP App Service `CRON_SECRET`):

```bash
cp local.settings.json.example local.settings.json
# Edit local.settings.json → set CRON_SECRET
```

### 2. Storage emulator (required for timer triggers locally)

Timer functions need `AzureWebJobsStorage`. Either:

- Install and run [Azurite](https://learn.microsoft.com/azure/storage/common/storage-use-azurite): `azurite --silent`
- Or paste your Function App’s storage connection string into `local.settings.json`

### 3. Run locally

Use Node 20 LTS (Azure Functions does not support Node 25 yet):

```bash
nvm use 20   # if using nvm
npm start    # func start — http://localhost:7071
```

Test ping:

```bash
curl "http://localhost:7071/api/ping?name=NutraAxis"
```

## Deploy to Azure

```bash
az login
func azure functionapp publish nutra-forecast-tool
```

## Azure App Settings (Function App → Configuration)

| Name | Value |
|------|--------|
| `WEBSITE_TIME_ZONE` | `America/Chicago` |
| `NUTRAAXIS_CRON_URL` | `https://nutraaxisweb.azurewebsites.net/cron/weekly-demand.php` |
| `CRON_SECRET` | Same as PHP App Service |
| `AzureWebJobsStorage` | (set at provision time) |

## PHP App Service settings (for `/function-test/`)

| Name | Value |
|------|--------|
| `AZURE_FUNCTION_APP_URL` | `https://nutra-forecast-tool-czaxf0eydta6aeeg.eastus2-01.azurewebsites.net` |
| `AZURE_FUNCTION_APP_KEY` | Function key from portal → Functions → ping → Function keys |
