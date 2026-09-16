/**
 * Overlay FLOW volunteer openings onto JOIN/DRAHT venues via draht_id === venue.id.
 *
 * @param {object[]} venues
 * @param {object[]} openings
 */
export function attachVenueNeeds(venues, openings) {
  /** @type {Map<number, object>} */
  const byDraht = new Map()
  for (const opening of openings) {
    for (const id of opening.draht_ids || []) {
      const n = Number(id)
      if (Number.isFinite(n) && n > 0) byDraht.set(n, opening)
    }
  }

  return venues.map((venue) => {
    const opening = byDraht.get(Number(venue.id))
    if (!opening) {
      return {
        ...venue,
        seeking: false,
        helper_search: null,
        public_url: venue.frontendUrl || null,
      }
    }
    return {
      ...venue,
      seeking: !!opening.seeking,
      helper_search: opening.helper_search ?? null,
      public_url: opening.public_url || venue.frontendUrl || null,
    }
  })
}

/**
 * @param {object} venue
 * @returns {string[]}
 */
export function venueOpenRoles(venue) {
  const scopes = venue?.helper_search?.scopes
  if (!Array.isArray(scopes)) return []
  const out = []
  for (const scope of scopes) {
    for (const role of scope.roles || []) {
      if (role && !out.includes(role)) out.push(role)
    }
  }
  return out
}
