import axios from 'axios'
import { getToken, isAuthenticated, updateToken } from '@/auth/keycloak'

function flowApiBaseUrl() {
  if (import.meta.env.DEV) {
    return '/flow-api'
  }
  const raw = (import.meta.env.VITE_FLOW_API_URL || '/flow-api').replace(/\/$/, '')
  return raw || '/flow-api'
}

const client = axios.create({
  baseURL: flowApiBaseUrl(),
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
 * Public list of FLOW events currently advertising open volunteer roles.
 * @returns {Promise<{ data: object[] }>}
 */
export async function fetchVolunteerOpenings() {
  const res = await client.get('/public/volunteer-openings')
  const body = res.data ?? {}
  const list = Array.isArray(body.data) ? body.data : []
  return { data: list }
}

export async function fetchSharePointStatus() {
  const res = await client.get('/public/sharepoint/status')
  return res.data ?? {}
}

export async function fetchSharePointDocuments(itemId = null) {
  const params = itemId ? { item_id: itemId } : {}
  const res = await client.get('/public/sharepoint/documents', { params })
  return res.data ?? {}
}

export async function streamSharePointFile(driveId, itemId) {
  const res = await client.get('/public/sharepoint/documents-file-stream', {
    params: { drive_id: driveId, item_id: itemId },
    responseType: 'blob',
  })
  const blob = res?.data
  return blob && blob.size > 0 ? blob : null
}

/**
 * @param {{ event_id: number, role: string, first_name: string, last_name: string, email: string, mobile?: string, message?: string }} payload
 */
export async function submitVolunteerInquiry(payload) {
  const res = await client.post('/public/volunteer-inquiries', payload)
  return res.data ?? {}
}
