<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import GlassField from '@hands-on/glass/field'
import GlassInput from '@hands-on/glass/input'
import {
  fetchHeroSharePointConfig,
  testHeroSharePointConnection,
  updateHeroSharePointConfig,
} from '@/services/api'

const { t } = useI18n()

const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const loadError = ref('')
const saveError = ref('')
const notice = ref('')
const testResult = ref(null)
const config = ref({})
const folderUrl = ref('')

const dirty = computed(() => (folderUrl.value.trim() || null) !== (config.value.folder_url || null))

function errorText(err, fallback) {
  const data = err?.response?.data
  const firstFieldError = data?.errors ? Object.values(data.errors).flat()[0] : ''
  return firstFieldError || data?.error || data?.message || fallback
}

function applyConfig(next) {
  config.value = next || {}
  folderUrl.value = config.value.folder_url || ''
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    applyConfig(await fetchHeroSharePointConfig())
  } catch (err) {
    loadError.value = errorText(err, t('admin.loadError'))
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  saveError.value = ''
  notice.value = ''
  testResult.value = null
  try {
    applyConfig(await updateHeroSharePointConfig(folderUrl.value.trim()))
    notice.value = t('admin.saved')
  } catch (err) {
    saveError.value = errorText(err, t('admin.saveError'))
  } finally {
    saving.value = false
  }
}

async function test() {
  testing.value = true
  notice.value = ''
  testResult.value = null
  try {
    const result = await testHeroSharePointConnection()
    testResult.value = { ok: true, text: t('admin.testOk', { folder: result.folder_name || '–', count: result.item_count ?? 0 }) }
    applyConfig(await fetchHeroSharePointConfig())
  } catch (err) {
    testResult.value = { ok: false, text: errorText(err, t('admin.testError')) }
  } finally {
    testing.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="hero-page admin-page liquid-surface-scope">
    <section class="admin-hero liquid-surface liquid-surface--accent">
      <h1>{{ t('admin.title') }}</h1>
      <p class="hero-lead">{{ t('admin.lead') }}</p>
    </section>

    <section class="hero-card liquid-surface-inner admin-card">
      <h2 class="admin-card__title">
        <i class="bi bi-folder2" aria-hidden="true" />
        {{ t('admin.sharepointTitle') }}
      </h2>
      <p class="admin-card__lead">{{ t('admin.sharepointLead') }}</p>

      <p v-if="loading" class="admin-status">
        <i class="bi bi-arrow-repeat spin" aria-hidden="true" />
        {{ t('admin.loading') }}
      </p>
      <p v-else-if="loadError" class="admin-status admin-status--error" role="alert">
        <i class="bi bi-exclamation-circle" aria-hidden="true" />
        {{ loadError }}
        <button type="button" class="btn btn-secondary btn-sm" @click="load">{{ t('admin.retry') }}</button>
      </p>

      <form v-else class="admin-form" @submit.prevent="save">
        <p v-if="!config.has_credentials" class="admin-status admin-status--warn">
          <i class="bi bi-exclamation-triangle" aria-hidden="true" />
          {{ t('admin.noCredentials') }}
        </p>

        <GlassField
          :label="t('admin.folderUrl')"
          :hint="t('admin.folderUrlHint')"
          :error="saveError"
          for-id="hero-sharepoint-folder"
        >
          <GlassInput
            id="hero-sharepoint-folder"
            v-model="folderUrl"
            type="url"
            placeholder="https://….sharepoint.com/:f:/s/…"
            autocomplete="off"
          />
        </GlassField>

        <p v-if="config.folder_name && !dirty" class="admin-meta">
          {{ t('admin.currentFolder', { folder: config.folder_name }) }}
        </p>
        <p v-else-if="!config.folder_url && !dirty" class="admin-meta">{{ t('admin.off') }}</p>

        <div class="admin-actions">
          <button type="submit" class="btn btn-primary" :disabled="saving || !dirty">
            {{ saving ? t('admin.saving') : t('admin.save') }}
          </button>
          <button
            type="button"
            class="btn btn-secondary"
            :disabled="testing || dirty || !config.configured"
            @click="test"
          >
            {{ testing ? t('admin.testing') : t('admin.test') }}
          </button>
        </div>

        <p v-if="notice" class="admin-status admin-status--ok" role="status">
          <i class="bi bi-check-circle" aria-hidden="true" />
          {{ notice }}
        </p>
        <p
          v-if="testResult"
          class="admin-status"
          :class="testResult.ok ? 'admin-status--ok' : 'admin-status--error'"
          role="status"
        >
          <i class="bi" :class="testResult.ok ? 'bi-check-circle' : 'bi-exclamation-circle'" aria-hidden="true" />
          {{ testResult.text }}
        </p>
      </form>
    </section>
  </div>
</template>

<style scoped>
.admin-page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}
.admin-hero {
  padding: 1.5rem 1.75rem;
  border-radius: var(--radius-lg);
}
.admin-card__title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 0 0.45rem;
  font-size: 1.0625rem;
  font-weight: 700;
  letter-spacing: -0.015em;
}
.admin-card__title .bi {
  color: var(--color-accent);
}
.admin-card__lead {
  margin: 0 0 1rem;
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  line-height: 1.5;
}
.admin-form {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  max-width: 44rem;
}
.admin-meta {
  margin: 0;
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}
.admin-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
}
.admin-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  font-size: var(--text-sm);
}
.admin-status--ok .bi {
  color: var(--color-accent);
}
.admin-status--warn .bi {
  color: #d97706;
}
.admin-status--error {
  color: var(--color-danger);
}
.spin {
  animation: admin-spin 0.8s linear infinite;
}
@keyframes admin-spin {
  to { transform: rotate(360deg); }
}
</style>
