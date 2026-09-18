<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalDismiss } from '@hands-on/glass/venues'
import GlassField from '@hands-on/glass/field'
import GlassInput from '@hands-on/glass/input'
import { authenticated, getUserProfile } from '@/auth/keycloak'
import { submitVolunteerInquiry } from '@/services/flow'

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

function prefillFromUser() {
  const user = authenticated.value ? getUserProfile() : null
  const given = String(user?.givenName || '').trim()
  const family = String(user?.familyName || '').trim()
  if (given || family) {
    form.first_name = given
    form.last_name = family
  } else {
    const name = String(user?.name || '').trim()
    const parts = name.split(/\s+/).filter(Boolean)
    form.first_name = parts.length > 1 ? parts.slice(0, -1).join(' ') : name
    form.last_name = parts.length > 1 ? parts.at(-1) : ''
  }
  form.email = user?.email || ''
  form.mobile = ''
  form.message = ''
}

watch(show, (open) => {
  submitError.value = ''
  done.value = false
  submitting.value = false
  if (open) prefillFromUser()
})

useModalDismiss(show, {
  dialogRef: dialogEl,
  onClose: () => emit('close'),
})

function onBackdropClick(e) {
  if (e.target === e.currentTarget) emit('close')
}

const canSubmit = computed(() => {
  return (
    form.first_name.trim() &&
    form.last_name.trim() &&
    form.email.trim() &&
    !submitting.value
  )
})

async function onSubmit() {
  if (!canSubmit.value || !props.venue?.flow_event_id) return
  submitting.value = true
  submitError.value = ''
  try {
    await submitVolunteerInquiry({
      event_id: props.venue.flow_event_id,
      role: props.role,
      first_name: form.first_name.trim(),
      last_name: form.last_name.trim(),
      email: form.email.trim(),
      mobile: form.mobile.trim() || undefined,
      message: form.message.trim() || undefined,
    })
    done.value = true
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
              class="inquiry-close"
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
            <button type="button" class="btn btn-primary" @click="emit('close')">
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

            <GlassField :label="t('inquiry.firstName')" required for-id="inquiry-first">
              <GlassInput id="inquiry-first" v-model="form.first_name" required autocomplete="given-name" />
            </GlassField>
            <GlassField :label="t('inquiry.lastName')" required for-id="inquiry-last">
              <GlassInput id="inquiry-last" v-model="form.last_name" required autocomplete="family-name" />
            </GlassField>
            <GlassField :label="t('inquiry.email')" required for-id="inquiry-email">
              <GlassInput id="inquiry-email" v-model="form.email" type="email" required autocomplete="email" />
            </GlassField>
            <GlassField :label="t('inquiry.mobile')" :hint="t('inquiry.mobileHint')" for-id="inquiry-mobile">
              <GlassInput id="inquiry-mobile" v-model="form.mobile" type="tel" autocomplete="tel" />
            </GlassField>
            <GlassField :label="t('inquiry.message')" for-id="inquiry-message">
              <GlassInput id="inquiry-message" v-model="form.message" type="textarea" rows="4" />
            </GlassField>

            <p v-if="submitError" class="inquiry-error" role="alert">{{ submitError }}</p>

            <div class="inquiry-actions">
              <button type="button" class="btn btn-secondary" @click="emit('close')">
                {{ t('inquiry.cancel') }}
              </button>
              <button type="submit" class="btn btn-primary" :disabled="!canSubmit">
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
  width: min(32rem, 100%);
  max-height: min(90vh, 44rem);
  overflow: auto;
  padding: 1.25rem 1.35rem 1.4rem;
}
.inquiry-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.85rem;
}
.inquiry-title {
  margin: 0;
  font-size: 1.2rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}
.inquiry-close {
  width: var(--touch);
  height: var(--touch);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}
.inquiry-body {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}
.inquiry-lead,
.inquiry-partner {
  margin: 0;
  font-size: var(--text-sm);
  line-height: 1.5;
  color: var(--color-text-muted);
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
}
.inquiry-success .bi {
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
</style>
