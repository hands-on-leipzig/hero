import axios from 'axios'

function flowApiBaseUrl() {
  const raw = (import.meta.env.VITE_FLOW_API_URL || '/flow-api').replace(/\/$/, '')
  return raw || '/flow-api'
}

const client = axios.create({
  baseURL: flowApiBaseUrl(),
  headers: { Accept: 'application/json' },
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
