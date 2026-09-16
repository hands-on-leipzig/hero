# Deploy

GitHub Actions (`.github/workflows/deploy.yml`), same pattern as JOIN:

| Trigger | Target | URL | Server folder |
|---------|--------|-----|----------------|
| Merge / push to `main` | Test | https://test.hero.hands-on-technology.org | `hero-test` |
| Publish a GitHub Release | Prod | https://hero.hands-on-technology.org | `hero-prod` |
| Actions → Deploy → Run workflow | either | — | as above |

Folders are relative to the SSH user’s home. Manual deploy: **Actions → Deploy → Run workflow**.

## Secrets

Copy the SSH secrets from JOIN (`hands-on-leipzig/node`). Paths are hardcoded in the workflow (do not set `TEST_DEPLOY_PATH` / `DEPLOY_PATH`).

**Test** (`TEST_DEPLOY_*`):

- `TEST_DEPLOY_HOST`
- `TEST_DEPLOY_USER`
- `TEST_DEPLOY_KEY`
- `TEST_DEPLOY_PORT` (optional, default `22`)

**Prod** (`DEPLOY_*`):

- `DEPLOY_HOST`
- `DEPLOY_USER`
- `DEPLOY_KEY`
- `DEPLOY_PORT` (optional, default `22`)

CI also checks out private `hands-on-leipzig/glass` with `GITHUB_TOKEN`. On the glass repo, allow Actions access from **hands-on-leipzig/hero** (same setting JOIN already uses).

## SPA reload

The built `dist/` includes `.htaccess` (from `public/.htaccess`). Apache: enable `mod_rewrite`. Nginx: add `nginx.snippet.conf` (`try_files $uri $uri/ /index.html;`).

CI writes `/build-info.json` (`builtAt` + `sha`) before `npm run build` / `build:test`.

## Optional release e-mail

Set repository variable `DEPLOY_NOTIFY_ENABLED=true` and the same Graph notify secrets as JOIN (`DEPLOY_NOTIFY_*`). Only runs on a published GitHub Release.
