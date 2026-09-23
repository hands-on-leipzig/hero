import { computed, ref } from 'vue'
import Keycloak from 'keycloak-js'

const keycloakConfig = {
  url: import.meta.env.VITE_KEYCLOAK_URL || 'https://sso.hands-on-technology.org',
  realm: import.meta.env.VITE_KEYCLOAK_REALM || 'master',
  clientId: import.meta.env.VITE_KEYCLOAK_CLIENT_ID || 'hero',
}

export const keycloak = new Keycloak(keycloakConfig)

/** Reactive flag so the shell updates after silent SSO or a login redirect. */
export const authenticated = ref(false)

/** Account profile from Keycloak (`loadUserProfile`), not only JWT claims. */
export const userProfile = ref(null)

/** True after the first profile sync attempt (success or fallback). */
export const profileSynced = ref(false)

let initPromise = null
let refreshTimer = null
let profilePromise = null

function syncAuth() {
  authenticated.value = !!keycloak.authenticated
  if (!keycloak.authenticated) {
    userProfile.value = null
    profileSynced.value = true
    profilePromise = null
  }
}

function startTokenRefresh() {
  if (refreshTimer) return
  refreshTimer = window.setInterval(() => {
    if (!keycloak.authenticated) return
    keycloak.updateToken(30).then(syncAuth).catch(syncAuth)
  }, 20000)
}

function firstAttribute(attributes, keys) {
  if (!attributes || typeof attributes !== 'object') return ''
  for (const key of keys) {
    const raw = attributes[key]
    const value = Array.isArray(raw) ? raw[0] : raw
    const text = String(value ?? '').trim()
    if (text) return text
  }
  return ''
}

function mapAccountProfile(account, token) {
  const first = String(account?.firstName ?? token?.given_name ?? '').trim()
  const last = String(account?.lastName ?? token?.family_name ?? '').trim()
  const email = String(account?.email ?? token?.email ?? '').trim()
  const username = String(account?.username ?? token?.preferred_username ?? '').trim()
  const fullName = [first, last].filter(Boolean).join(' ') || String(token?.name ?? '').trim()
  return {
    name: fullName || username || 'Volunteer',
    givenName: first,
    familyName: last,
    email,
    username,
    picture: String(token?.picture ?? '').trim(),
    phone: firstAttribute(account?.attributes, ['phoneNumber', 'phone', 'mobile', 'mobileNumber']),
  }
}

function applyTokenProfile() {
  if (!keycloak.authenticated || !keycloak.tokenParsed) {
    userProfile.value = null
    return
  }
  userProfile.value = mapAccountProfile(null, keycloak.tokenParsed)
}

export function syncUserProfile() {
  if (profilePromise) return profilePromise
  if (!keycloak.authenticated) {
    userProfile.value = null
    profileSynced.value = true
    return Promise.resolve(null)
  }
  applyTokenProfile()
  profilePromise = keycloak
    .loadUserProfile()
    .then((account) => {
      userProfile.value = mapAccountProfile(account, keycloak.tokenParsed)
      profileSynced.value = true
      return userProfile.value
    })
    .catch((err) => {
      console.error('Keycloak profile sync failed', err)
      applyTokenProfile()
      profileSynced.value = true
      profilePromise = null
      return userProfile.value
    })
  return profilePromise
}

/** Missing Keycloak account fields needed for signed-in inquiries. */
export const profileGaps = computed(() => {
  if (!authenticated.value || !profileSynced.value) return []
  const profile = userProfile.value
  const gaps = []
  if (!profile?.givenName) gaps.push('firstName')
  if (!profile?.familyName) gaps.push('lastName')
  if (!profile?.email) gaps.push('email')
  return gaps
})

keycloak.onReady = () => {
  syncAuth()
  if (keycloak.authenticated) {
    startTokenRefresh()
    syncUserProfile()
  } else {
    profileSynced.value = true
  }
}
keycloak.onAuthSuccess = () => {
  syncAuth()
  startTokenRefresh()
  profilePromise = null
  syncUserProfile()
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
    .then(async (ok) => {
      syncAuth()
      if (ok) {
        startTokenRefresh()
        await syncUserProfile()
      } else {
        profileSynced.value = true
      }
      return ok
    })
    .catch((err) => {
      syncAuth()
      profileSynced.value = true
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

function dolibarrContactClaimCandidates() {
  const fromEnv = import.meta.env.VITE_KEYCLOAK_DOLIBARR_CONTACT_CLAIM
  const list = []
  if (fromEnv && String(fromEnv).trim()) list.push(String(fromEnv).trim())
  list.push('dolibarr_contact_id', 'dolibarrContactId', 'dolibarr_id', 'dolibarrId')
  return [...new Set(list)]
}

/** DRAHT/Dolibarr contact id from the Keycloak access token. */
export function getDrahtContactId() {
  if (!keycloak.authenticated || !keycloak.tokenParsed) return null
  const parsed = keycloak.tokenParsed
  for (const claim of dolibarrContactClaimCandidates()) {
    const value = parsed[claim]
    if (value == null || value === '') continue
    const id = parseInt(value, 10)
    if (Number.isFinite(id) && id > 0) return id
  }
  return null
}

/** Realm role that will gate volunteer-only pages later. Not enforced yet. */
const VOLUNTEER_REALM_ROLE = 'volunteer'

export function hasVolunteerRole() {
  if (!keycloak.authenticated || !keycloak.tokenParsed) return false
  const roles = keycloak.tokenParsed.realm_access?.roles
  return Array.isArray(roles) && roles.includes(VOLUNTEER_REALM_ROLE)
}

/** Realm or `hero` client role; the HERO backend checks the same role on `/api/admin/*`. */
const HERO_ADMIN_ROLE = 'hero_admin'

export const isHeroAdmin = computed(() => {
  if (!authenticated.value || !keycloak.tokenParsed) return false
  const parsed = keycloak.tokenParsed
  const roles = [
    ...(parsed.realm_access?.roles || []),
    ...(parsed.resource_access?.hero?.roles || []),
  ]
  return roles.includes(HERO_ADMIN_ROLE)
})

export function getUserProfile() {
  if (!authenticated.value) return null
  return userProfile.value
}

function currentPageRedirectUri() {
  return `${window.location.origin}${window.location.pathname || '/'}${window.location.search || ''}`
}

export async function login(redirectUri = currentPageRedirectUri()) {
  try {
    await initKeycloak({ onLoad: 'check-sso' })
  } catch (_) {
    // A failed silent check-sso still leaves the adapter usable for a full redirect.
  }
  try {
    await keycloak.login({ redirectUri, scope: 'openid profile email' })
  } catch (err) {
    console.error('Keycloak login failed', err)
    throw err
  }
}

const PHONE_ATTR_KEYS = ['phoneNumber', 'phone', 'mobile', 'mobileNumber']

function accountUrl() {
  const base = String(keycloak.authServerUrl || keycloakConfig.url || '').replace(/\/$/, '')
  const realm = keycloak.realm || keycloakConfig.realm
  return `${base}/realms/${realm}/account`
}

function messageFromAccountError(res, data) {
  if (typeof data === 'string' && data.trim()) return data.trim()
  if (Array.isArray(data?.errors)) {
    const parts = data.errors.map((item) => item?.errorMessage || item?.message).filter(Boolean)
    if (parts.length) return parts.join(' ')
  }
  if (data?.fieldErrors && typeof data.fieldErrors === 'object') {
    const parts = Object.values(data.fieldErrors).flat().filter(Boolean)
    if (parts.length) return parts.join(' ')
  }
  return (
    data?.errorMessage
    || data?.error_description
    || data?.error
    || data?.message
    || `HTTP ${res.status}`
  )
}

async function fetchAccountRaw() {
  await keycloak.updateToken(30)
  const res = await fetch(accountUrl(), {
    headers: {
      Authorization: `Bearer ${keycloak.token}`,
      Accept: 'application/json',
    },
  })
  const data = await res.json().catch(() => ({}))
  if (!res.ok) {
    const error = new Error(messageFromAccountError(res, data))
    error.status = res.status
    throw error
  }
  return data && typeof data === 'object' ? data : {}
}

function withPhoneAttribute(attributes, phone) {
  const next = { ...(attributes && typeof attributes === 'object' ? attributes : {}) }
  const existingKey = PHONE_ATTR_KEYS.find((key) => {
    const raw = next[key]
    if (Array.isArray(raw)) return raw.some((item) => String(item ?? '').trim())
    return String(raw ?? '').trim() !== ''
  }) || 'phoneNumber'
  for (const key of PHONE_ATTR_KEYS) {
    if (key !== existingKey) delete next[key]
  }
  const trimmed = String(phone ?? '').trim()
  if (trimmed) next[existingKey] = [trimmed]
  else delete next[existingKey]
  return next
}

/**
 * Write first name, last name, email and phone to the Keycloak account, then re-sync.
 * @param {{ firstName: string, lastName: string, email: string, phone?: string }} fields
 */
export async function updateUserProfile(fields) {
  if (!keycloak.authenticated) {
    throw new Error('Not authenticated')
  }
  const current = await fetchAccountRaw()
  const body = {
    ...current,
    firstName: String(fields.firstName ?? '').trim(),
    lastName: String(fields.lastName ?? '').trim(),
    email: String(fields.email ?? '').trim(),
    attributes: withPhoneAttribute(current.attributes, fields.phone),
  }
  await keycloak.updateToken(30)
  let res = await fetch(accountUrl(), {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${keycloak.token}`,
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(body),
  })
  if (res.status === 405) {
    res = await fetch(accountUrl(), {
      method: 'PUT',
      headers: {
        Authorization: `Bearer ${keycloak.token}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(body),
    })
  }
  if (!res.ok && res.status !== 204) {
    const data = await res.json().catch(() => ({}))
    const error = new Error(messageFromAccountError(res, data))
    error.status = res.status
    throw error
  }
  profilePromise = null
  await syncUserProfile()
  try {
    await keycloak.updateToken(70)
  } catch (_) {}
  return userProfile.value
}

export function openAccount() {
  const redirectUri = currentPageRedirectUri()
  try {
    const url = keycloak.createAccountUrl({ redirectUri })
    window.location.assign(url)
  } catch (err) {
    console.error('Keycloak account URL failed', err)
  }
}

export function logout() {
  const redirectUri = `${window.location.origin}/`
  if (!keycloak.authenticated) {
    authenticated.value = false
    userProfile.value = null
    profileSynced.value = true
    return
  }
  keycloak.logout({ redirectUri })
}

export function updateToken(minValidity = 30) {
  return keycloak.updateToken(minValidity)
}
