import { ref } from 'vue'
import Keycloak from 'keycloak-js'

const keycloakConfig = {
  url: import.meta.env.VITE_KEYCLOAK_URL || 'https://sso.hands-on-technology.org',
  realm: import.meta.env.VITE_KEYCLOAK_REALM || 'master',
  clientId: import.meta.env.VITE_KEYCLOAK_CLIENT_ID || 'hero',
}

export const keycloak = new Keycloak(keycloakConfig)

/** Reactive flag so the shell updates after silent SSO or a login redirect. */
export const authenticated = ref(false)

let initPromise = null
let refreshTimer = null

function syncAuth() {
  authenticated.value = !!keycloak.authenticated
}

function startTokenRefresh() {
  if (refreshTimer) return
  refreshTimer = window.setInterval(() => {
    if (!keycloak.authenticated) return
    keycloak.updateToken(30).then(syncAuth).catch(syncAuth)
  }, 20000)
}

keycloak.onReady = syncAuth
keycloak.onAuthSuccess = () => {
  syncAuth()
  startTokenRefresh()
}
keycloak.onAuthLogout = syncAuth
keycloak.onAuthRefreshError = syncAuth

/**
 * Initialize Keycloak. Call before mounting routed views.
 * @param {Object} options - { onLoad: 'login-required' | 'check-sso' }
 * @returns {Promise<boolean>} true if authenticated
 */
export function initKeycloak(options = {}) {
  if (initPromise) return initPromise
  const { onLoad = 'check-sso' } = options
  initPromise = keycloak
    .init({
      onLoad,
      checkLoginIframe: false,
      pkceMethod: 'S256',
      silentCheckSsoRedirectUri: `${window.location.origin}/silent-check-sso.html`,
    })
    .then((ok) => {
      syncAuth()
      if (ok) startTokenRefresh()
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

/** Realm role that will gate volunteer-only pages later. Not enforced yet. */
const VOLUNTEER_REALM_ROLE = 'volunteer'

export function hasVolunteerRole() {
  if (!keycloak.authenticated || !keycloak.tokenParsed) return false
  const roles = keycloak.tokenParsed.realm_access?.roles
  return Array.isArray(roles) && roles.includes(VOLUNTEER_REALM_ROLE)
}

export function getUserProfile() {
  if (!keycloak.authenticated || !keycloak.tokenParsed) return null
  const p = keycloak.tokenParsed
  const givenName = String(p.given_name ?? '').trim()
  const familyName = String(p.family_name ?? '').trim()
  const fullName = String(p.name ?? '').trim() || [givenName, familyName].filter(Boolean).join(' ')
  return {
    name: fullName || p.preferred_username || 'Volunteer',
    givenName,
    familyName,
    email: p.email ?? '',
    username: p.preferred_username ?? '',
    picture: p.picture ?? '',
  }
}

function currentPageRedirectUri() {
  return `${window.location.origin}${window.location.pathname || '/'}${window.location.search || ''}`
}

export async function login() {
  const redirectUri = currentPageRedirectUri()
  const options = { redirectUri, scope: 'openid profile email' }
  try {
    await initKeycloak({ onLoad: 'check-sso' })
    return keycloak.login(options)
  } catch (e) {
    try {
      const url = keycloak.createLoginUrl(options)
      window.location.assign(url)
    } catch (err) {
      console.error('Keycloak login failed', err)
      throw err
    }
  }
}

export function logout() {
  const redirectUri = `${window.location.origin}/`
  if (!keycloak.authenticated) {
    authenticated.value = false
    return
  }
  keycloak.logout({ redirectUri })
}

export function updateToken(minValidity = 30) {
  return keycloak.updateToken(minValidity)
}
