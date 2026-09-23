<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  DocumentsFolderList,
  DocumentOpeningOverlay,
  DocumentViewerModal,
  canViewInApp,
  isImageFileName,
  isPdfFileName,
  isSharePointHost,
  isUrlShortcutFileName,
  parseInternetShortcutUrl,
  readUrlShortcutText,
} from '@hands-on/glass/documents'
import {
  fetchSharePointDocuments,
  fetchSharePointStatus,
  streamSharePointFile,
} from '@/services/api'

const { t, locale } = useI18n()

const configured = ref(false)
const loading = ref(false)
const openingFile = ref(false)
const openingFileName = ref('')
const error = ref('')
const items = ref([])
const breadcrumbs = ref([])
const currentDriveId = ref(null)
const folderWebUrl = ref('')

const viewerOpen = ref(false)
const viewerUrl = ref('')
const viewerTitle = ref('')
const viewerMode = ref('pdf')
const viewerBlobUrl = ref('')

function closeViewer() {
  viewerOpen.value = false
  viewerUrl.value = ''
  viewerTitle.value = ''
  if (viewerBlobUrl.value) {
    URL.revokeObjectURL(viewerBlobUrl.value)
    viewerBlobUrl.value = ''
  }
}

function openBlob(blob, title, mode) {
  if (!blob || blob.size < 1) return false
  if (viewerBlobUrl.value) URL.revokeObjectURL(viewerBlobUrl.value)
  viewerBlobUrl.value = URL.createObjectURL(blob)
  viewerUrl.value = viewerBlobUrl.value
  viewerTitle.value = title
  viewerMode.value = mode
  viewerOpen.value = true
  return true
}

async function openDocumentFile(file) {
  const driveId = String(file?.drive_id || currentDriveId.value || '').trim()
  const itemId = String(file?.id || '').trim()
  const title = String(file?.name || t('docs.untitled'))

  if (driveId && itemId) {
    const blob = await streamSharePointFile(driveId, itemId)
    if (!blob) return false
    if (isPdfFileName(file.name) && blob.size >= 100) return openBlob(blob, title, 'pdf')
    if (isImageFileName(file.name)) return openBlob(blob, title, 'image')
    if (isUrlShortcutFileName(file.name)) {
      const targetUrl = parseInternetShortcutUrl(await readUrlShortcutText(blob))
      if (targetUrl) {
        window.open(targetUrl, '_blank', 'noopener,noreferrer')
        return true
      }
    }
    const blobUrl = URL.createObjectURL(blob)
    window.open(blobUrl, '_blank', 'noopener,noreferrer')
    setTimeout(() => URL.revokeObjectURL(blobUrl), 60_000)
    return true
  }

  const rawUrl = String(file?.web_url || '').trim()
  if (!rawUrl) return false
  if (canViewInApp(file.name) && isSharePointHost(rawUrl)) return false
  window.open(rawUrl, '_blank', 'noopener,noreferrer')
  return true
}

const canGoUp = computed(() => breadcrumbs.value.length > 1)

async function loadFolder(itemId = null) {
  loading.value = true
  error.value = ''
  try {
    const data = await fetchSharePointDocuments(itemId)
    configured.value = data.configured ?? false
    if (data.error) {
      error.value = data.error
      items.value = []
      return
    }
    items.value = data.items ?? []
    breadcrumbs.value = data.breadcrumbs ?? []
    currentDriveId.value = data.drive_id ?? null
    if (data.folder_web_url) folderWebUrl.value = data.folder_web_url
  } catch (err) {
    error.value = err?.response?.data?.error || t('docs.loadError')
    items.value = []
  } finally {
    loading.value = false
  }
}

async function openFolder(item) {
  await loadFolder(item.id)
}

async function openFile(item) {
  if (openingFile.value) return
  openingFile.value = true
  openingFileName.value = item.name
  error.value = ''
  try {
    const ok = await openDocumentFile(item)
    if (!ok) error.value = t('docs.openError')
  } finally {
    openingFile.value = false
    openingFileName.value = ''
  }
}

async function navigateTo(crumb) {
  await loadFolder(crumb.id)
}

async function goUp() {
  if (!canGoUp.value) return
  const parent = breadcrumbs.value[breadcrumbs.value.length - 2]
  await loadFolder(parent.id)
}

onMounted(async () => {
  try {
    const status = await fetchSharePointStatus()
    configured.value = status.configured ?? false
    if (status.folder_url) folderWebUrl.value = status.folder_url
    if (configured.value) await loadFolder(null)
  } catch {
    configured.value = false
    error.value = t('docs.loadError')
  }
})
</script>

<template>
  <section class="hero-card liquid-surface-inner docs-card">
    <h2 class="docs-card__title">
      <i class="bi bi-folder2" aria-hidden="true" />
      {{ t('docs.title') }}
    </h2>
    <p class="docs-card__lead">{{ t('docs.lead') }}</p>
    <DocumentsFolderList
      :configured="configured"
      :loading="loading"
      :opening-file="openingFile"
      :error="error"
      :items="items"
      :breadcrumbs="breadcrumbs"
      :folder-web-url="folderWebUrl"
      :locale="locale"
      :not-configured-text="t('docs.notConfigured')"
      :loading-text="t('docs.loading')"
      :empty-folder-text="t('docs.empty')"
      :root-label="t('docs.root')"
      :go-up-label="t('docs.goUp')"
      :open-folder-tab-label="t('docs.openFolder')"
      @open-folder="openFolder"
      @open-file="openFile"
      @navigate="navigateTo"
      @go-up="goUp"
      @go-root="loadFolder(null)"
    />
    <DocumentOpeningOverlay
      :open="openingFile"
      :title="t('docs.opening')"
      :file-name="openingFileName"
    />
    <DocumentViewerModal
      :show="viewerOpen"
      :url="viewerUrl"
      :title="viewerTitle"
      :mode="viewerMode"
      :close-label="t('venues.detailClose')"
      :empty-text="t('docs.emptyViewer')"
      @close="closeViewer"
    />
  </section>
</template>

<style scoped>
.docs-card__title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 0 0.45rem;
  font-size: 1.0625rem;
  font-weight: 700;
  letter-spacing: -0.015em;
}
.docs-card__title .bi {
  color: var(--color-accent);
}
.docs-card__lead {
  margin: 0 0 1rem;
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  line-height: 1.5;
}
</style>
