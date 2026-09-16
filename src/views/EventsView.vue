<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { VenuesCatalog, publicEventAbsoluteUrl, publicEventPathFromUrl } from '@hands-on/glass/venues'
import { fetchVolunteerOpenings } from '@/services/flow'
import { fetchPublicVenues } from '@/services/publicVenues'
import { attachVenueNeeds } from '@/utils/attachVenueNeeds'
import VenueNeeds from '@/components/VenueNeeds.vue'
import VolunteerInquiryModal from '@/components/VolunteerInquiryModal.vue'
import logoFll from '@/assets/FIRSTLego_IconVert_RGB.png'

const { t } = useI18n()
const router = useRouter()
const loading = ref(true)
const error = ref(null)
const openingsError = ref(false)
const venues = ref([])
const venuesMeta = ref({})
const selectedVenue = ref(null)
const onlySeeking = ref(false)
const inquiry = ref({ venue: null, role: '' })

const catalogVenues = computed(() => {
  if (!onlySeeking.value) return venues.value
  return venues.value.filter((venue) => venue.seeking)
})

watch(catalogVenues, (list) => {
  const selected = selectedVenue.value
  if (selected && !list.some((venue) => venue.id === selected.id)) {
    selectedVenue.value = null
  }
})

function openVenueDetail(venue) {
  if (!venue?.id) return
  const url = publicEventAbsoluteUrl(venue.public_url || '')
  const publicPath = publicEventPathFromUrl(url)
  if (publicPath) {
    router.push({
      name: 'events-event',
      params: { publicPath },
      query: { src: url, title: venue.name || '' },
    })
    return
  }
  selectedVenue.value = venue
}

function closeVenueDetail() {
  selectedVenue.value = null
}

function openInquiry(venue, role) {
  if (!venue?.flow_event_id || !role) return
  inquiry.value = { venue, role }
}

function closeInquiry() {
  inquiry.value = { venue: null, role: '' }
}

async function loadEvents() {
  loading.value = true
  error.value = null
  openingsError.value = false
  try {
    const venuesRes = await fetchPublicVenues()
    let openings = []
    try {
      openings = (await fetchVolunteerOpenings()).data
    } catch {
      openingsError.value = true
    }
    venues.value = attachVenueNeeds(venuesRes.data, openings)
    venuesMeta.value = venuesRes.meta || {}
  } catch (e) {
    error.value = e?.message || t('venues.loadError')
    venues.value = []
  } finally {
    loading.value = false
  }
}

onMounted(loadEvents)
</script>

<template>
  <div class="hero-page venues-page liquid-surface-scope">
    <main class="venues-main">
      <section class="venues-hero liquid-surface liquid-surface--accent">
        <div class="venues-hero-layout">
          <div class="venues-hero-main">
            <h1 class="venues-title">{{ t('events.title') }}</h1>
            <p class="hero-lead">{{ t('events.lead') }}</p>
            <label class="venues-filter">
              <input v-model="onlySeeking" type="checkbox">
              <span>{{ t('events.onlySeeking') }}</span>
            </label>
          </div>
          <div class="venues-hero-logo-wrap" aria-hidden="true">
            <img :src="logoFll" alt="" class="venues-hero-logo" decoding="async">
          </div>
        </div>
      </section>

      <div v-if="loading" class="venues-status liquid-surface">
        <i class="bi bi-arrow-repeat spin" aria-hidden="true" />
        {{ t('venues.loading') }}
      </div>
      <div v-else-if="error" class="venues-status venues-status-error liquid-surface">
        <i class="bi bi-exclamation-circle" aria-hidden="true" />
        {{ error }}
        <button type="button" class="btn btn-secondary btn-sm" @click="loadEvents">
          {{ t('venues.retry') }}
        </button>
      </div>
      <template v-else>
        <p v-if="openingsError" class="venues-hint">
          <i class="bi bi-exclamation-circle" aria-hidden="true" />
          {{ t('events.openingsLoadError') }}
        </p>
        <p v-if="venuesMeta.noActiveSeason" class="venues-hint venues-hint-warn">
          <i class="bi bi-calendar-x" aria-hidden="true" />
          {{ t('venues.noActiveSeason') }}
        </p>
        <p v-else-if="!venues.length" class="venues-hint">
          <i class="bi bi-info-circle" aria-hidden="true" />
          {{ t('venues.emptyListHint') }}
        </p>
        <p v-else-if="onlySeeking && !catalogVenues.length" class="venues-hint">
          <i class="bi bi-funnel" aria-hidden="true" />
          {{ t('events.noSearchResults') }}
        </p>
        <VenuesCatalog
          v-if="catalogVenues.length"
          :venues="catalogVenues"
          :selected-venue="selectedVenue"
          @select="openVenueDetail"
          @close="closeVenueDetail"
        >
          <template #event-extra="{ venue }">
            <VenueNeeds :venue="venue" @select-role="(role) => openInquiry(venue, role)" />
          </template>
          <template #detail-extra="{ venue }">
            <div class="venue-needs-detail">
              <p class="venue-needs-detail__label">{{ t('events.openRoles') }}</p>
              <VenueNeeds :venue="venue" @select-role="(role) => openInquiry(venue, role)" />
            </div>
          </template>
          <template #detail-links="{ venue }">
            <a
              v-if="venue.public_url"
              :href="venue.public_url"
              class="venue-detail-link"
              rel="noopener noreferrer"
              target="_blank"
            >
              {{ t('events.viewEvent') }}
              <i class="bi bi-box-arrow-up-right" aria-hidden="true" />
            </a>
          </template>
        </VenuesCatalog>
        <VolunteerInquiryModal
          :key="`${inquiry.venue?.id || ''}-${inquiry.role}`"
          :venue="inquiry.venue"
          :role="inquiry.role"
          @close="closeInquiry"
        />
      </template>
    </main>
  </div>
</template>

<style scoped>
.venues-main {
  max-width: 72rem;
  margin: 0 auto;
  padding: 1.5rem 1.25rem 3rem;
}

.venues-hero {
  margin-bottom: 1.5rem;
  padding: 1.5rem 1.35rem;
}

.venues-hero-layout {
  display: flex;
  align-items: stretch;
  gap: 1.25rem 1.75rem;
}

.venues-hero-main {
  flex: 1;
  min-width: 0;
}

.venues-title {
  font-size: var(--text-3xl);
  font-weight: 700;
  letter-spacing: -0.02em;
  margin: 0 0 0.75rem;
  line-height: 1.2;
}

.venues-hero-logo-wrap {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.venues-hero-logo {
  height: 7.5rem;
  width: auto;
  max-width: min(7.5rem, 28vw);
  object-fit: contain;
}

.venues-filter {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  margin-top: 1.1rem;
  font-size: var(--text-sm);
  font-weight: 600;
  cursor: pointer;
  user-select: none;
}

.venues-status {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 2rem;
  color: var(--color-text-muted);
}

.venues-status-error {
  flex-direction: column;
}

.venues-hint {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.85rem 1rem;
  margin: 0 0 1.25rem;
  border-radius: var(--radius-lg);
  background: var(--liquid-tile-bg);
  border: 1px solid var(--liquid-border);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.venues-hint-warn {
  border-color: rgba(255, 122, 0, 0.35);
  color: var(--color-text);
}

.venue-needs-detail {
  margin: 0.85rem 0 0;
}

.venue-needs-detail__label {
  margin: 0 0 0.35rem;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.venue-detail-link {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-accent);
}

.spin {
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 640px) {
  .venues-hero-layout {
    flex-direction: column;
  }

  .venues-hero-logo-wrap {
    align-self: flex-end;
  }

  .venues-hero-logo {
    height: 4.5rem;
  }
}
</style>
