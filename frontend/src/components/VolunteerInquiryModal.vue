<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalDismiss } from '@hands-on/glass/venues'
import GlassField from '@hands-on/glass/field'
import GlassInput from '@hands-on/glass/input'
import { authenticated, getUserProfile, updateUserProfile } from '@/auth/keycloak'
import { submitVolunteerInquiry } from '@/services/api'

const props = defineProps({
  venue: { type: Object, default: null },
  role: { type: String, default: '' },
})

const emit = defineEmits(['close'])

const { t } = useI18n()
const dialogEl = ref(null)
const submitting = ref(false)
const submitError = ref('')
const done = ref(false)
const saveToProfile = ref(false)
const profileSaveError = ref('')
const profileSaved = ref(false)

const form = reactive({
  first_name: '',
  last_name: '',
  email: '',
  mobile: '',
  message: '',
})

const show = computed(() => !!props.venue && !!props.role)
const eventName = computed(() => props.venue?.name || '')
const partnerName = computed(() => props.venue?.partner || '')

function namesFromProfile(user) {
  const given = String(user?.givenName || '').trim()
  const family = String(user?.familyName || '').trim()
  if (given || family) return { first_name: given, last_name: family }
  const name = String(user?.name || '').trim()
  const parts = name.split(/\s+/).filter(Boolean)
  return {
    first_name: parts.length > 1 ? parts.slice(0, -1).join(' ') : name,
    last_name: parts.length > 1 ? parts.at(-1) : '',
  }
}

function applyProfileToForm() {
  const user = getUserProfile()
  const names = namesFromProfile(user)
  form.first_name = names.first_name
  form.last_name = names.last_name
  form.email = String(user?.email || '').trim()
  form.mobile = String(user?.phone || '').trim()
  form.message = ''
}

function resetGuestForm() {
  form.first_name = ''
  form.last_name = ''
  form.email = ''
  form.mobile = ''
  form.message = ''
}

watch(
  show,
  (open) => {
    submitError.value = ''
    profileSaveError.value = ''
    profileSaved.value = false
    saveToProfile.value = false
    done.value = false
    submitting.value = false
    if (!open) return
    if (authenticated.value) applyProfileToForm()
    else resetGuestForm()
  },
  { immediate: true },
)

useModalDismiss(show, {
  dialogRef: dialogEl,
  onClose: () => emit('close'),
})

function onBackdropClick(e) {
  if (e.target === e.currentTarget) emit('close')
}

const canSubmit = computed(() => {
  if (submitting.value) return false
  return Boolean(form.first_name.trim() && form.last_name.trim() && form.email.trim())
})

async function onSubmit() {
  if (!canSubmit.value || !props.venue?.flow_event_id) return
  submitting.value = true
  submitError.value = ''
  profileSaveError.value = ''
  profileSaved.value = false
  try {
    const payload = {
      event_id: props.venue.flow_event_id,
      role: props.role,
      first_name: form.first_name.trim(),
      last_name: form.last_name.trim(),
      email: form.email.trim(),
    }
    if (form.mobile.trim()) payload.mobile = form.mobile.trim()
    if (form.message.trim()) payload.message = form.message.trim()
    await submitVolunteerInquiry(payload)
    done.value = true

    if (authenticated.value && saveToProfile.value) {
      try {
        await updateUserProfile({
          firstName: form.first_name.trim(),
          lastName: form.last_name.trim(),
          email: form.email.trim(),
          phone: form.mobile.trim(),
        })
        profileSaved.value = true
      } catch (err) {
        profileSaveError.value = err?.message || t('inquiry.profileSaveError')
      }
    }
  } catch (err) {
    const data = err?.response?.data
    submitError.value =
      data?.message ||
      data?.error ||
      (data?.errors && Object.values(data.errors).flat().join(' ')) ||
      t('inquiry.submitError')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="inquiry-modal">
      <div
        v-if="show"
        class="inquiry-backdrop"
        role="dialog"
        aria-modal="true"
        :aria-label="t('inquiry.title')"
        @click="onBackdropClick"
      >
        <div
          ref="dialogEl"
          class="inquiry-dialog liquid-surface-scope liquid-surface liquid-surface--accent"
          tabindex="-1"
          @click.stop
        >
          <header class="inquiry-head">
            <h2 class="inquiry-title">{{ t('inquiry.title') }}</h2>
            <button
              type="button"
              class="glass-btn-icon inquiry-close"
              :aria-label="t('venues.detailClose')"
              @click="emit('close')"
            >
              <i class="bi bi-x-lg" aria-hidden="true" />
            </button>
          </header>

          <div v-if="done" class="inquiry-body">
            <p class="inquiry-success">
              <i class="bi bi-check-circle" aria-hidden="true" />
              {{ t('inquiry.success') }}
            </p>
            <p v-if="profileSaved" class="inquiry-success-sub">
              <i class="bi bi-person-check" aria-hidden="true" />
              {{ t('inquiry.profileSaved') }}
            </p>
            <p v-if="profileSaveError" class="inquiry-error" role="alert">{{ profileSaveError }}</p>
            <button type="button" class="glass-btn-accent" @click="emit('close')">
              {{ t('inquiry.close') }}
            </button>
          </div>

          <form v-else class="inquiry-body" @submit.prevent="onSubmit">
            <p class="inquiry-lead">
              {{ t('inquiry.lead', { role, event: eventName }) }}
            </p>
            <p v-if="partnerName" class="inquiry-partner">
              {{ t('inquiry.partner', { partner: partnerName }) }}
            </p>

            <div class="inquiry-fields liquid-surface-inner">
              <div class="glass-field-row">
                <GlassField :label="t('inquiry.firstName')" required for-id="inquiry-first">
                  <GlassInput id="inquiry-first" v-model="form.first_name" required autocomplete="given-name" />
                </GlassField>
                <GlassField :label="t('inquiry.lastName')" required for-id="inquiry-last">
                  <GlassInput id="inquiry-last" v-model="form.last_name" required autocomplete="family-name" />
                </GlassField>
              </div>
              <GlassField :label="t('inquiry.email')" required for-id="inquiry-email">
                <GlassInput id="inquiry-email" v-model="form.email" type="email" required autocomplete="email" />
              </GlassField>
              <GlassField :label="t('inquiry.mobile')" :hint="t('inquiry.mobileHint')" for-id="inquiry-mobile">
                <GlassInput id="inquiry-mobile" v-model="form.mobile" type="tel" autocomplete="tel" />
              </GlassField>
              <GlassField :label="t('inquiry.message')" for-id="inquiry-message">
                <GlassInput id="inquiry-message" v-model="form.message" type="textarea" rows="3" />
              </GlassField>
            </div>

            <label v-if="authenticated" class="inquiry-sync">
              <input type="checkbox" v-model="saveToProfile" class="inquiry-sync-checkbox">
              <span class="inquiry-sync-text">
                <strong>{{ t('inquiry.saveToProfile') }}</strong>
                <small>{{ t('inquiry.saveToProfileHint') }}</small>
              </span>
            </label>

            <p v-if="submitError" class="inquiry-error" role="alert">{{ submitError }}</p>

            <div class="inquiry-actions">
              <button type="button" class="glass-btn-secondary" @click="emit('close')">
                {{ t('inquiry.cancel') }}
              </button>
              <button type="submit" class="glass-btn-accent" :disabled="!canSubmit">
                {{ submitting ? t('inquiry.submitting') : t('inquiry.submit') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.inquiry-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.25rem;
  background: var(--liquid-modal-scrim-bg);
}
.inquiry-dialog {
  --inquiry-fg: #1c1917;
  --inquiry-fg-muted: #57534e;
  width: min(34rem, 100%);
  max-height: min(90vh, 46rem);
  overflow: auto;
  padding: 1.35rem 1.5rem 1.5rem;
  color: var(--inquiry-fg);
}
.inquiry-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.9rem;
}
.inquiry-title {
  margin: 0;
  font-size: 1.2rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--inquiry-fg);
}
.inquiry-close {
  flex-shrink: 0;
}
.inquiry-body {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}
.inquiry-lead,
.inquiry-partner {
  margin: 0;
  font-size: var(--text-sm);
  line-height: 1.5;
  color: var(--inquiry-fg-muted);
}
.inquiry-fields {
  display: flex;
  flex-direction: column;
  padding: 1.1rem 1.1rem 0.25rem;
}
.inquiry-fields :deep(.glass-field__label),
.inquiry-fields :deep(.glass-field__hint) {
  color: var(--inquiry-fg-muted);
}
.inquiry-fields :deep(.glass-field__label) {
  color: var(--inquiry-fg);
}
.inquiry-sync {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  padding: 0.8rem 1rem;
  border-radius: var(--radius);
  background: var(--color-accent-soft);
  border: 1px solid color-mix(in srgb, var(--color-accent) 30%, transparent);
  cursor: pointer;
}
.inquiry-sync-checkbox {
  margin-top: 0.2rem;
  width: 1.05rem;
  height: 1.05rem;
  flex-shrink: 0;
  accent-color: var(--color-accent);
  cursor: pointer;
}
.inquiry-sync-text {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  font-size: var(--text-sm);
  color: var(--inquiry-fg);
}
.inquiry-sync-text strong {
  font-weight: 650;
}
.inquiry-sync-text small {
  color: var(--inquiry-fg-muted);
  font-size: 0.8125rem;
  line-height: 1.4;
}
.inquiry-error {
  margin: 0;
  font-size: var(--text-sm);
  color: #b91c1c;
}
.inquiry-success {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  margin: 0;
  line-height: 1.5;
  color: var(--inquiry-fg);
}
.inquiry-success .bi {
  color: var(--color-accent);
  margin-top: 0.15rem;
}
.inquiry-success-sub {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  margin: -0.4rem 0 0;
  font-size: var(--text-sm);
  line-height: 1.5;
  color: var(--inquiry-fg-muted);
}
.inquiry-success-sub .bi {
  color: var(--color-accent);
  margin-top: 0.15rem;
}
.inquiry-actions {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 0.55rem;
  margin-top: 0.35rem;
}
.inquiry-modal-enter-active,
.inquiry-modal-leave-active {
  transition: opacity 0.18s ease;
}
.inquiry-modal-enter-from,
.inquiry-modal-leave-to {
  opacity: 0;
}

@media (max-width: 560px) {
  .inquiry-fields :deep(.glass-field-row) {
    flex-direction: column;
  }
}
</style>

<style>
/* Teleported to body: theme tokens without scoped-attribute mismatch */
html[data-theme='dark'] .inquiry-dialog {
  --inquiry-fg: #fafaf9;
  --inquiry-fg-muted: #d6d3d1;
}
</style>
