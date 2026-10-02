<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import BrandText from '@hands-on/glass/brand-text'
import { VenueEventRow, sortVenues, venueDisplayName } from '@hands-on/glass/venues'
import { accessDenied, authenticated, login, logout } from '@/auth/keycloak'
import { fetchVolunteerOpenings } from '@/services/api'
import { fetchPublicVenues } from '@/services/publicVenues'
import { attachVenueNeeds, venueOpenRoles } from '@/utils/attachVenueNeeds'
import { venueEventRoute } from '@/utils/venueEventRoute'
import SharePointDocumentsCard from '@/components/SharePointDocumentsCard.vue'
import VenueNeeds from '@/components/VenueNeeds.vue'
import VolunteerInquiryModal from '@/components/VolunteerInquiryModal.vue'
import logoFll from '@/assets/FIRSTLego_IconVert_RGB.png'

const UPCOMING_LIMIT = 6
const ONLY_SEEKING_KEY = 'hero.events.onlySeeking'

const { t, locale } = useI18n()
const router = useRouter()
const loading = ref(true)
const loadFailed = ref(false)
const venues = ref([])
const inquiry = ref({ venue: null, role: '' })

const today = new Date().toISOString().slice(0, 10)

const upcoming = computed(() => {
  const future = venues.value.filter((venue) => !venue.date || String(venue.date) >= today)
  return sortVenues(future, locale.value, 'date')
})

const seeking = computed(() => upcoming.value.filter((venue) => venue.seeking))
const showsSeeking = computed(() => seeking.value.length > 0)
const listed = computed(() => (showsSeeking.value ? seeking.value : upcoming.value).slice(0, UPCOMING_LIMIT))
const hiddenCount = computed(() => (showsSeeking.value ? seeking.value.length : upcoming.value.length) - listed.value.length)

const openRoleCount = computed(() => seeking.value.reduce((sum, venue) => sum + venueOpenRoles(venue).length, 0))
const nextEvent = computed(() => upcoming.value.find((venue) => venue.date) || null)
const nextEventDate = computed(() => {
  const raw = nextEvent.value?.date
  if (!raw) return ''
  const date = new Date(`${raw}T12:00:00`)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleDateString(locale.value === 'en' ? 'en-GB' : 'de-DE', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
  })
})

function showSeekingEvents() {
  sessionStorage.setItem(ONLY_SEEKING_KEY, '1')
  router.push({ name: 'events' })
}

function venueHref(venue) {
  const target = venueEventRoute(venue)
  return target ? router.resolve(target).href : ''
}

function openVenue(venue) {
  const target = venueEventRoute(venue)
  router.push(target || { name: 'events' })
}

function openInquiry(venue, role) {
  if (!venue?.flow_event_id || !role) return
  inquiry.value = { venue, role }
}

function closeInquiry() {
  inquiry.value = { venue: null, role: '' }
}

onMounted(async () => {
  try {
    const venuesRes = await fetchPublicVenues()
    let openings = []
    try {
      openings = (await fetchVolunteerOpenings()).data
    } catch {
      // Without openings the list still shows the next events.
    }
    venues.value = attachVenueNeeds(venuesRes.data, openings)
  } catch {
    loadFailed.value = true
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="hero-page home liquid-surface-scope">
    <div v-if="accessDenied" class="home-auth liquid-surface">
      <span>{{ t('auth.noAccess') }}</span>
      <button type="button" class="btn btn-secondary btn-sm" @click="logout()">
        <i class="bi bi-box-arrow-right" aria-hidden="true" />
        {{ t('auth.logout') }}
      </button>
    </div>

    <section class="home-overview liquid-surface liquid-surface--accent" :aria-busy="loading">
      <div class="home-overview__head">
        <div>
          <p class="home-overview__kicker"><BrandText :text="t('home.overviewKicker')" /></p>
          <h1 class="home-overview__title">{{ t('home.overviewTitle') }}</h1>
        </div>
        <img :src="logoFll" alt="" class="home-overview__logo" aria-hidden="true" decoding="async" />
      </div>
      <div class="home-stats">
        <RouterLink to="/events" class="home-stat">
          <span class="home-stat__value">{{ loading ? '–' : upcoming.length }}</span>
          <span class="home-stat__label">{{ t('home.statEvents') }}</span>
        </RouterLink>
        <button type="button" class="home-stat" :disabled="loading || !seeking.length" @click="showSeekingEvents">
          <span class="home-stat__value">{{ loading ? '–' : seeking.length }}</span>
          <span class="home-stat__label">{{ t('home.statSeeking') }}</span>
        </button>
        <button type="button" class="home-stat" :disabled="loading || !openRoleCount" @click="showSeekingEvents">
          <span class="home-stat__value">{{ loading ? '–' : openRoleCount }}</span>
          <span class="home-stat__label">{{ t('home.statRoles') }}</span>
        </button>
        <component
          :is="nextEvent ? 'a' : 'div'"
          :href="nextEvent ? venueHref(nextEvent) || undefined : undefined"
          class="home-stat home-stat--next"
          @click.prevent="nextEvent && openVenue(nextEvent)"
        >
          <span class="home-stat__value home-stat__value--date">{{ loading ? '–' : nextEventDate || '–' }}</span>
          <span class="home-stat__label">
            {{ t('home.statNext') }}<template v-if="nextEvent">: {{ venueDisplayName(nextEvent, locale) }}</template>
          </span>
        </component>
      </div>
    </section>

    <section class="home-section">
      <header class="home-section__head">
        <h2 class="home-section__title">
          {{ showsSeeking || loading ? t('home.seekingTitle') : t('home.upcomingTitle') }}
        </h2>
        <RouterLink to="/events" class="home-section__more">
          {{ t('home.allEvents') }}
          <i class="bi bi-arrow-right" aria-hidden="true" />
        </RouterLink>
      </header>

      <div v-if="loading" class="home-status liquid-surface">
        <i class="bi bi-arrow-repeat spin" aria-hidden="true" />
        {{ t('venues.loading') }}
      </div>
      <p v-else-if="loadFailed" class="home-status liquid-surface">
        {{ t('venues.loadError') }}
      </p>
      <p v-else-if="!listed.length" class="home-status liquid-surface">
        {{ t('home.noUpcoming') }}
      </p>
      <template v-else>
        <ul class="home-list liquid-surface liquid-surface--radius-lg">
          <li v-for="venue in listed" :key="venue.id">
            <VenueEventRow :venue="venue" :href="venueHref(venue)" @select="openVenue">
              <VenueNeeds
                v-if="venue.seeking"
                :venue="venue"
                @select-role="(role) => openInquiry(venue, role)"
              />
            </VenueEventRow>
          </li>
        </ul>
        <p v-if="hiddenCount > 0" class="home-more-count">
          <RouterLink to="/events">{{ t('home.moreCount', { count: hiddenCount }) }}</RouterLink>
        </p>
      </template>
    </section>

    <SharePointDocumentsCard v-if="authenticated" />
    <div v-else-if="!accessDenied" class="home-auth liquid-surface">
      <span>{{ t('home.docsAfterLogin') }}</span>
      <button type="button" class="btn btn-primary btn-sm" @click="login()">
        <i class="bi bi-box-arrow-in-right" aria-hidden="true" />
        {{ t('auth.signInWithSso') }}
      </button>
    </div>

    <VolunteerInquiryModal
      :key="`${inquiry.venue?.id || ''}-${inquiry.role}`"
      :venue="inquiry.venue"
      :role="inquiry.role"
      @close="closeInquiry"
    />
  </div>
</template>

<style scoped>
.home {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  max-width: 72rem;
  margin: 0 auto;
  padding: 1.5rem 1.25rem 3rem;
}

.home-overview {
  padding: 1.35rem 1.35rem 1.25rem;
}

.home-overview__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin: 0 0 1.1rem;
}

.home-overview__kicker {
  margin: 0 0 0.3rem;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--color-accent);
}

.home-overview__title {
  margin: 0;
  font-size: var(--text-3xl, 1.875rem);
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.2;
}

.home-overview__logo {
  height: 3.5rem;
  width: auto;
  object-fit: contain;
}

.home-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(0, 1.6fr);
  gap: 0.65rem;
}

.home-stat {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
  margin: 0;
  padding: 0.8rem 0.9rem;
  border: 1px solid var(--liquid-border);
  border-radius: var(--radius-lg);
  background: var(--liquid-tile-bg);
  color: inherit;
  font: inherit;
  text-align: left;
  text-decoration: none;
  cursor: pointer;
}

.home-stat:is(a, button):not(:disabled):hover {
  border-color: var(--color-accent);
}

.home-stat:disabled,
div.home-stat {
  cursor: default;
}

.home-stat__value {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.1;
  letter-spacing: -0.03em;
}

.home-stat__value--date {
  font-size: 1.25rem;
  line-height: 1.5;
}

.home-stat__label {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

@media (max-width: 720px) {
  .home-stats {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .home-stat--next {
    grid-column: 1 / -1;
  }

  .home-overview__logo {
    display: none;
  }
}

.home-section__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 1rem;
  margin: 0 0 0.75rem;
}

.home-section__title {
  margin: 0;
  font-size: var(--text-2xl, 1.5rem);
  font-weight: 700;
  letter-spacing: -0.02em;
}

.home-section__more {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  flex-shrink: 0;
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-accent);
  text-decoration: none;
}

.home-list {
  overflow: clip;
  list-style: none;
  margin: 0;
  padding: 0;
}

.home-list > li + li {
  border-top: 1px solid var(--color-border);
}

.home-more-count {
  margin: 0.6rem 0 0;
  font-size: var(--text-sm);
}

.home-more-count a {
  color: var(--color-text-muted);
}

.home-status {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  margin: 0;
  padding: 2rem;
  color: var(--color-text-muted);
}

.home-auth {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.85rem 1rem;
  font-size: var(--text-sm);
  font-weight: 600;
}

.spin {
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
