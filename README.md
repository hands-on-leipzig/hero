# HERO

**HERO** is the volunteer platform for FIRST LEGO League (HANDS on TECHNOLOGY).

- **JOIN** is for coaches
- **FLOW** is for regional partners
- **HERO** is for volunteers

MVP: a public landing page and the JOIN venues catalog (map + list), with volunteer needs from FLOW overlaid on matching events (`draht_id`). The public pages load without Keycloak. SSO is only contacted when someone clicks “Sign in” (or when Keycloak redirects back after login).

## Project setup

```sh
npm install
npm run dev
```

Dev server: [http://localhost:5175](http://localhost:5175). FLOW is proxied at `/flow-api` (see `VITE_FLOW_PROXY_TARGET`). Public venues are loaded from DRAHT (`VITE_DRAHT_API_URL`), same catalog as JOIN.

Sibling checkout of [`glass`](https://github.com/hands-on-leipzig/glass) is required (`@hands-on/glass`: `file:../glass`).

## Configuration

Copy `.env.example` to `.env`:

| Variable | Purpose |
|----------|---------|
| `VITE_KEYCLOAK_URL` | Keycloak base URL |
| `VITE_KEYCLOAK_REALM` | Realm (default `master`) |
| `VITE_KEYCLOAK_CLIENT_ID` | Public SPA client (`hero`) |
| `VITE_FLOW_API_URL` | FLOW API prefix (`/flow-api` in dev) |
| `VITE_FLOW_PROXY_TARGET` | Laravel origin for the Vite proxy (dev only) |
| `VITE_DRAHT_API_URL` | DRAHT API prefix for the public venues catalog |

### Keycloak client (`hero`)

Authorization Code + PKCE, public client (no secret):

- **Standard flow** ON
- **Valid redirect URIs**: `http://localhost:5175/*`, `https://test.hero.hands-on-technology.org/*`, `https://hero.hands-on-technology.org/*`
- **Web origins**: the same hosts without path

Optional later: realm role `volunteer` (`hasVolunteerRole()` is already in `src/auth/keycloak.js`). The MVP does not hide any page behind that role.

### FLOW

Partners publish volunteer search in FLOW (“Suche nach Helfer:innen”). HERO shows the JOIN venue catalog and overlays open roles when FLOW has published helper search for the matching event.

## Build

```sh
npm run build
npm run build:test
```

## Deploy

Same pipeline as JOIN (see `deploy/README.md`):

- Merge to `main` → [test.hero.hands-on-technology.org](https://test.hero.hands-on-technology.org) (`hero-test`)
- Publish a GitHub Release → [hero.hands-on-technology.org](https://hero.hands-on-technology.org) (`hero-prod`)
