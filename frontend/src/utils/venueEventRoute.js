import { publicEventAbsoluteUrl, publicEventPathFromUrl } from '@hands-on/glass/venues'

/**
 * In-app route to the embedded public event page, or null when the venue has none.
 *
 * @param {object} venue
 */
export function venueEventRoute(venue) {
  const url = publicEventAbsoluteUrl(venue?.public_url || '')
  const publicPath = publicEventPathFromUrl(url)
  if (!publicPath) return null
  return {
    name: 'events-event',
    params: { publicPath },
    query: { src: url, title: venue.name || '' },
  }
}
