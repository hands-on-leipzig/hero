# Deploy

GitHub Actions (`.github/workflows/deploy.yml` → `deploy-reusable.yml`), same pattern as FLOW:

| Trigger | Target | URL | App folder (SSH home) |
|---------|--------|-----|------------------------|
| Merge / push to `main` | Test | https://test.hero.hands-on-technology.org | `public_html/hero-test` |
| Publish a GitHub Release | Prod | https://hero.hands-on-technology.org | `public_html/hero-prod` |
| Actions → Deploy → Run workflow | either | — | as above |

CI builds the SPA (`frontend/`), copies it into `backend/public/`, runs the backend tests, installs production Composer dependencies and rsyncs `backend/` to `deploy-tmp/hero-test|hero-prod`. `backend/scripts/deploy-finalize.sh` then moves it into the app folder (keeping `.env` and `storage/`), runs migrations and caches config/routes. A health check calls `/api/ping`.

## One-time server setup

Per host (test and prod):

1. **PHP 8.2+** with `pdo_sqlite`, `openssl`, `mbstring`, `curl` for the CLI and the web server.
2. **Web root** → `<app folder>/public` (previously the app folder itself, when HERO was static). Apache: `mod_rewrite` on; `public/.htaccess` from Laravel sends everything that is not a file to `index.php`. Nginx: `try_files $uri $uri/ /index.php?$query_string;` plus PHP-FPM.
3. **`<app folder>/.env`** from `backend/.env.example`:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `APP_KEY` (`php artisan key:generate --show` locally)
   - `FLOW_API_URL` (`https://dev.flow.hands-on-technology.org/api` on test, `https://flow.hands-on-technology.org/api` on prod)
   - `SHAREPOINT_TENANT_ID`, `SHAREPOINT_CLIENT_ID`, `SHAREPOINT_CLIENT_SECRET` (HERO's Entra app, application permission `Sites.Read.All` + admin consent)

The SQLite database lives in `storage/app/hero.sqlite` and survives deploys.

## Secrets

Copy the SSH secrets from JOIN (`hands-on-leipzig/node`).

**Test** (`TEST_DEPLOY_*`): `TEST_DEPLOY_HOST`, `TEST_DEPLOY_USER`, `TEST_DEPLOY_KEY`, `TEST_DEPLOY_PORT` (optional, default `22`)

**Prod** (`DEPLOY_*`): `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_KEY`, `DEPLOY_PORT` (optional, default `22`)

CI also checks out private `hands-on-leipzig/glass` with `GITHUB_TOKEN`. On the glass repo, allow Actions access from **hands-on-leipzig/hero** (same setting JOIN already uses).

CI writes `/build-info.json` (`builtAt` + `sha`) before the SPA build.

## Optional release e-mail

Set repository variable `DEPLOY_NOTIFY_ENABLED=true` and the same Graph notify secrets as JOIN (`DEPLOY_NOTIFY_*`). Only runs on a published GitHub Release.
