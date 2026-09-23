<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { openAccount, profileGaps, profileSynced } from '@/auth/keycloak'

const { t, locale } = useI18n()
const dismissed = ref(false)

const visible = computed(() => {
  return profileSynced.value && profileGaps.value.length > 0 && !dismissed.value
})

const fieldsLabel = computed(() => {
  const labels = profileGaps.value.map((key) => t(`auth.profileField.${key}`))
  if (labels.length <= 1) return labels[0] || ''
  const head = labels.slice(0, -1).join(', ')
  const last = labels.at(-1)
  return locale.value === 'de' ? `${head} und ${last}` : `${head} and ${last}`
})
</script>

<template>
  <div v-if="visible" class="profile-nudge" role="status">
    <i class="bi bi-person-exclamation profile-nudge__icon" aria-hidden="true" />
    <p class="profile-nudge__text">
      {{ t('auth.profileNudge', { fields: fieldsLabel }) }}
    </p>
    <button type="button" class="btn btn-primary btn-sm" @click="openAccount">
      {{ t('auth.profileNudgeCta') }}
    </button>
    <button
      type="button"
      class="profile-nudge__dismiss"
      :aria-label="t('auth.profileNudgeDismiss')"
      @click="dismissed = true"
    >
      {{ t('auth.profileNudgeDismiss') }}
    </button>
  </div>
</template>

<style scoped>
.profile-nudge {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.65rem 0.85rem;
  max-width: 72rem;
  margin: 1.15rem auto 0;
  padding: 0.85rem 1.1rem;
  border-radius: var(--radius-lg);
  border: 1px solid color-mix(in srgb, var(--color-accent) 35%, var(--color-border));
  background: var(--color-accent-soft);
  color: var(--color-text);
}

.profile-nudge__icon {
  font-size: 1.25rem;
  color: var(--color-accent);
}

.profile-nudge__text {
  margin: 0;
  flex: 1 1 14rem;
  font-size: var(--text-sm);
  font-weight: 600;
  line-height: 1.45;
}

.profile-nudge__dismiss {
  margin-left: auto;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font: inherit;
  font-size: var(--text-sm);
  font-weight: 600;
  cursor: pointer;
  min-height: var(--touch);
  padding: 0 0.35rem;
}

.profile-nudge__dismiss:hover {
  color: var(--color-text);
}

@media (max-width: 720px) {
  .profile-nudge {
    margin-left: 1.25rem;
    margin-right: 1.25rem;
  }
}
</style>
