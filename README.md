# HERO

**HERO** is the volunteer platform for FIRST LEGO League (HANDS on TECHNOLOGY).

- **JOIN** is for coaches
- **FLOW** is for regional partners
- **HERO** is for volunteers

MVP: a public landing page and the JOIN venues catalog (map + list), with volunteer needs from FLOW overlaid on matching events (`draht_id`). Clicking an open role opens a form that asks the regional partner. SharePoint documents (HERO's own folder; UI from `@hands-on/glass/documents`) are shown only after SSO login. Public pages stay usable without an account. On load, HERO silently checks Keycloak (`check-sso`); “Sign in with SSO” starts the login redirect. After login, name and email prefill the volunteer inquiry form.

## Layout

| Folder | What |
|--------|------|
| `frontend/` | Vue SPA (Vite) |
| `backend/` | Laravel API (`/api/*`); in production it also serves the built SPA |

The SPA only talks to its own backend (plus Keycloak and the public DRAHT venue catalog). The backend owns the SharePoint integration and admin settings, and calls FLOW's public API for volunteer openings and inquiries.

## Project setup

Sibling checkout of [`glass`](https://github.com/hands-on-leipzig/glass) is required (`frontend/package.json`: `file:../../glass`).

```sh
# backend (PHP 8.2+)
cd backend
composer install
cp .env.example .env && php artisan key:generate
touch storage/app/hero.sqlite && php artisan migrate
php artisan serve --port=8001

# frontend
cd frontend
npm install
npm run dev
```

Dev server: [http://localhost:5175](http://localhost:5175). Vite proxies `/api` to the backend (`VITE_BACKEND_PROXY_TARGET`, default `http://localhost:8001`).

Backend tests: `cd backend && php artisan test`.

## Configuration

### Frontend (`frontend/.env`)

| Variable | Purpose |
|----------|---------|
| `VITE_KEYCLOAK_URL` | Keycloak base URL |
| `VITE_KEYCLOAK_REALM` | Realm (default `master`) |
| `VITE_KEYCLOAK_CLIENT_ID` | Public SPA client (`hero`) |
| `VITE_BACKEND_PROXY_TARGET` | Laravel origin for the Vite proxy (dev only) |
| `VITE_DRAHT_API_URL` | DRAHT API prefix for the public venues catalog |

### Backend (`backend/.env`)

| Variable | Purpose |
|----------|---------|
| `KEYCLOAK_ISSUER` | Realm URL; tokens are verified against its JWKS |
| `KEYCLOAK_CLIENT_ID` | Only tokens issued to this client (`azp`) are accepted (`hero`) |
| `FLOW_API_URL` | FLOW public API (`https://dev.flow…/api` on test) |
| `SHAREPOINT_TENANT_ID` / `SHAREPOINT_CLIENT_ID` / `SHAREPOINT_CLIENT_SECRET` | HERO's own Entra app, application permission `Sites.Read.All` |

The SharePoint folder link itself is set in the admin UI and stored in the backend's SQLite database (`storage/app/hero.sqlite`).

### Keycloak client (`hero`)

Authorization Code + PKCE, public client (no secret):

- **Standard flow** ON
- **Valid redirect URIs**: `http://localhost:5175/*`, `https://test.hero.hands-on-technology.org/*`, `https://hero.hands-on-technology.org/*` (covers `/silent-check-sso.html`)
- **Web origins**: the same hosts without path (or `+`). Without them the browser blocks the token exchange after login and HERO stays signed out.
- **PKCE** required; no client secret on the SPA

Optional later: realm role `volunteer` (`hasVolunteerRole()` is already in `frontend/src/auth/keycloak.js`). The MVP does not hide any page behind that role.

**Admin:** role `hero_admin` (realm role or client role on `hero`) shows the Admin entry and `/admin`, where the SharePoint folder for the start page is set. The backend checks the same role on `/api/admin/*`.

### FLOW

Partners publish volunteer search in FLOW (“Suche nach Helfer:innen”). HERO shows the JOIN venue catalog and overlays open roles when FLOW has published helper search for the matching event. Inquiries are forwarded to FLOW, where the partner answers them.

## Deploy

See `deploy/README.md`:

- Merge to `main` → [test.hero.hands-on-technology.org](https://test.hero.hands-on-technology.org) (`public_html/hero-test`)
- Publish a GitHub Release → [hero.hands-on-technology.org](https://hero.hands-on-technology.org) (`public_html/hero-prod`)
