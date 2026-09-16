<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { venueOpenRoles } from '@/utils/attachVenueNeeds'

const props = defineProps({
  venue: { type: Object, required: true },
})

const { t } = useI18n()

const roles = computed(() => venueOpenRoles(props.venue))

const needsLabel = computed(() => {
  if (props.venue.seeking) {
    const n = roles.value.length
    return n === 1 ? t('events.needsOne') : t('events.needsCount', { count: n })
  }
  if (props.venue.helper_search) return t('events.needsFilled')
  return t('events.needsUnpublished')
})
</script>

<template>
  <span
    v-if="venue.seeking || venue.helper_search"
    class="venues-needs"
    :class="{ 'venues-needs--open': venue.seeking }"
  >
    <span v-if="venue.seeking && roles.length" class="venues-needs__chips">
      <span v-for="role in roles" :key="role" class="role-chip">{{ role }}</span>
    </span>
    <span v-else>{{ needsLabel }}</span>
  </span>
</template>

<style scoped>
.venues-needs {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.venues-needs--open {
  width: 100%;
}

.venues-needs__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
}
</style>
