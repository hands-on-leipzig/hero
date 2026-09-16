<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  event: {
    type: Object,
    required: true,
  },
})

const { t, locale } = useI18n()

const dateLabel = computed(() => {
  if (!props.event?.date) return t('events.dateTbd')
  const start = new Date(`${props.event.date}T00:00:00`)
  if (Number.isNaN(start.getTime())) return t('events.dateTbd')
  const fmt = new Intl.DateTimeFormat(locale.value === 'en' ? 'en-GB' : 'de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
  const days = Math.max(Number(props.event.days || 1), 1)
  if (days > 1) {
    const end = new Date(start)
    end.setDate(end.getDate() + days - 1)
    return `${fmt.format(start)}–${fmt.format(end)}`
  }
  return fmt.format(start)
})

const placeLine = computed(() => {
  return [props.event.partner, props.event.region].filter(Boolean).join(' · ')
})

const roles = computed(() => {
  const scopes = props.event.helper_search?.scopes
  if (!Array.isArray(scopes)) return []
  const out = []
  for (const scope of scopes) {
    for (const role of scope.roles || []) {
      if (role && !out.includes(role)) out.push(role)
    }
  }
  return out
})
</script>

<template>
  <article class="hero-card liquid-surface-inner opening-card">
    <header class="opening-card__head">
      <h2 class="opening-card__title">{{ event.name }}</h2>
      <p class="opening-card__meta">
        <span><i class="bi bi-calendar3" aria-hidden="true" /> {{ dateLabel }}</span>
        <span v-if="placeLine"><i class="bi bi-geo-alt" aria-hidden="true" /> {{ placeLine }}</span>
      </p>
    </header>

    <section v-if="roles.length" class="opening-card__roles">
      <h3 class="opening-card__roles-label">{{ t('events.openRoles') }}</h3>
      <ul class="opening-card__chips">
        <li v-for="role in roles" :key="role">
          <span class="role-chip">{{ role }}</span>
        </li>
      </ul>
    </section>

    <a
      v-if="event.public_url"
      class="btn btn-primary btn-sm opening-card__cta"
      :href="event.public_url"
      target="_blank"
      rel="noopener noreferrer"
    >
      {{ t('events.viewEvent') }}
      <i class="bi bi-box-arrow-up-right" aria-hidden="true" />
    </a>
  </article>
</template>

<style scoped>
.opening-card {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  height: 100%;
}

.opening-card__title {
  margin: 0 0 0.4rem;
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--color-text);
}

.opening-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 1rem;
  margin: 0;
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.opening-card__meta .bi {
  margin-right: 0.3rem;
}

.opening-card__roles-label {
  margin: 0 0 0.45rem;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--color-text-subtle);
}

.opening-card__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.opening-card__cta {
  align-self: flex-start;
  margin-top: auto;
}
</style>
