import axios from 'axios'
import { getToken, isAuthenticated, updateToken } from '@/auth/keycloak'

/** HERO backend (Laravel). Same origin in production; Vite proxies `/api` in dev. */
const client = axios.create({
  baseURL: (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, ''),
  withCredentials: false,
  headers: { Accept: 'application/json' },
})

client.interceptors.request.use(async (config) => {
  if (!isAuthenticated()) return config
  try {
    await updateToken(30)
  } catch (_) {}
  const token = getToken()
  if (token) {
    config.headers = config.headers || {}
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

/**
 * Events currently advertising open volunteer roles (from FLOW, via the HERO backend).
 * @returns {Promise<{ data: object[] }>}
 */
export async function fetchVolunteerOpenings() {
  const res = await client.get('/volunteer-openings')
  const body = res.data ?? {}
  const list = Array.isArray(body.data) ? body.data : []
  return { data: list }
}

export async function fetchSharePointStatus() {
  const res = await client.get('/sharepoint/status')
  return res.data ?? {}
}

export async function fetchSharePointDocuments(itemId = null) {
  const params = itemId ? { item_id: itemId } : {}
  const res = await client.get('/sharepoint/documents', { params })
  return res.data ?? {}
}

export async function streamSharePointFile(driveId, itemId) {
  const res = await client.get('/sharepoint/documents-file-stream', {
    params: { drive_id: driveId, item_id: itemId },
    responseType: 'blob',
  })
  const blob = res?.data
  return blob && blob.size > 0 ? blob : null
}

/**
 * Admin: SharePoint folder shown on the start page (hero_admin role).
 * @returns {Promise<{ folder_url: string|null, folder_name: string|null, has_credentials: boolean, configured: boolean }>}
 */
export async function fetchHeroSharePointConfig() {
  const res = await client.get('/admin/sharepoint')
  return res.data ?? {}
}

/** @param {string|null} folderUrl */
export async function updateHeroSharePointConfig(folderUrl) {
  const res = await client.put('/admin/sharepoint', { folder_url: folderUrl || null })
  return res.data?.config ?? {}
}

/** @returns {Promise<{ success: boolean, folder_name?: string, item_count?: number, error?: string }>} */
export async function testHeroSharePointConnection() {
  const res = await client.post('/admin/sharepoint/test')
  return res.data ?? {}
}

/**
 * @param {{ event_id: number, role: string, first_name: string, last_name: string, email: string, mobile?: string, message?: string }} payload
 */
export async function submitVolunteerInquiry(payload) {
  const res = await client.post('/volunteer-inquiries', payload)
  return res.data ?? {}
}
