import { ref } from 'vue'
import Keycloak from 'keycloak-js'

const keycloakConfig = {
  url: import.meta.env.VITE_KEYCLOAK_URL || 'https://sso.hands-on-technology.org',
  realm: import.meta.env.VITE_KEYCLOAK_REALM || 'master',
  clientId: import.meta.env.VITE_KEYCLOAK_CLIENT_ID || 'hero',
}

export const keycloak = new Keycloak(keycloakConfig)

/** Reactive flag so the shell updates after a login redirect is processed. */
export const authenticated = ref(false)

let initPromise = null

function syncAuth() {
  authenticated.value = !!keycloak.authenticated
}

/**
 * True when the current URL is an OIDC redirect back from Keycloak.
 * Public pages must not contact Keycloak unless this is set.
 */
export function hasOidcCallback() {
  if (typeof window === 'undefined') return false
  const query = new URLSearchParams(window.location.search)
  const hash = new URLSearchParams(window.location.hash.replace(/^#/, ''))
  return [query, hash].some((params) => (params.has('code') && params.has('state')) || params.has('error'))
}

/**
 * Initialize Keycloak. Does not run on public page load.
 * Call from login() or when completing an OIDC callback.
 */
export function initKeycloak() {
  if (initPromise) return initPromise
  initPromise = keycloak
    .init({
      checkLoginIframe: false,
      pkceMethod: 'S256',
    })
    .then((ok) => {
      syncAuth()
      return ok
    })
    .catch((err) => {
      initPromise = null
      syncAuth()
      throw err
    })
  return initPromise
}

export function getToken() {
  return keycloak.token
}

export function isAuthenticated() {
  return authenticated.value
}

/** Realm role that will gate volunteer-only pages later. Not enforced in the MVP. */
const VOLUNTEER_REALM_ROLE = 'volunteer'

export function hasVolunteerRole() {
  if (!keycloak.authenticated || !keycloak.tokenParsed) return false
  const roles = keycloak.tokenParsed.realm_access?.roles
  return Array.isArray(roles) && roles.includes(VOLUNTEER_REALM_ROLE)
}

export function getUserProfile() {
  if (!keycloak.authenticated || !keycloak.tokenParsed) return null
  const p = keycloak.tokenParsed
  return {
    name: p.name ?? p.preferred_username ?? 'Volunteer',
    email: p.email ?? '',
    username: p.preferred_username ?? '',
    picture: p.picture ?? '',
  }
}

/** User-initiated only. Public browsing never calls this. */
export async function login() {
  const redirectUri = `${window.location.origin}${window.location.pathname || '/'}`
  await initKeycloak()
  return keycloak.login({ redirectUri })
}

export function logout() {
  if (!keycloak.authenticated) {
    authenticated.value = false
    return
  }
  keycloak.logout({ redirectUri: `${window.location.origin}/` })
}

export function updateToken(minValidity = 30) {
  return keycloak.updateToken(minValidity)
}
