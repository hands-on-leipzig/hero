<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { keycloak } from '@/auth/keycloak'
import AppSwitchMark from '@hands-on/glass/components/AppSwitchMark.vue'
import logoHero from '@/assets/HERO_v1.1.png'

const open = ref(false)
const rootEl = ref(null)

function roles() {
  const parsed = keycloak?.tokenParsed
  const list = [...(parsed?.realm_access?.roles || [])]
  const clients = parsed?.resource_access || {}
  for (const client of Object.values(clients)) {
    if (Array.isArray(client?.roles)) list.push(...client.roles)
  }
  return list.map((role) => String(role).toLowerCase())
}

function appUrl(name) {
  const host = window.location.hostname
  const tier = host.startsWith('dev.') ? 'dev.' : host.startsWith('test.') ? 'test.' : ''
  return `https://${tier}${name}.hands-on-technology.org`
}

const choices = computed(() => {
  const have = roles()
  const flow = have.some((role) => (
    role === 'flow_user'
    || role === 'flow-user'
    || role === 'flow_admin'
    || role === 'flow-admin'
    || role === 'flow-tester'
    || role === 'flow_tester'
  ))
  const wise = flow || have.some((role) => role.includes('scor') || role.includes('wise'))
  const items = [{ id: 'hero', label: 'HERO', href: '/', current: true, logo: logoHero }]
  if (flow) {
    items.push({ id: 'flow', label: 'FLOW', href: appUrl('flow'), current: false, logo: `${appUrl('flow')}/flow/flow.png` })
  }
  if (wise) {
    items.push({ id: 'wise', label: 'WISE', href: `${appUrl('flow')}/scor`, current: false, logo: `${appUrl('flow')}/flow/wise.png` })
  }
  if (have.includes('coach')) {
    items.push({ id: 'join', label: 'JOIN', href: appUrl('join'), current: false, logo: `${appUrl('flow')}/flow/join.png` })
  }
  return items
})

const canSwitch = computed(() => choices.value.length > 1)

function close() {
  open.value = false
}

function onPointer(event) {
  if (!open.value) return
  if (event.target instanceof Node && rootEl.value?.contains(event.target)) return
  close()
}

function onKey(event) {
  if (event.key === 'Escape') close()
}

watch(open, (isOpen) => {
  if (isOpen) {
    document.addEventListener('pointerdown', onPointer)
    document.addEventListener('keydown', onKey)
  } else {
    document.removeEventListener('pointerdown', onPointer)
    document.removeEventListener('keydown', onKey)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onPointer)
  document.removeEventListener('keydown', onKey)
})
</script>

<template>
  <div v-if="canSwitch" ref="rootEl" class="hero-switch">
    <button
      type="button"
      class="hero-switch__btn"
      aria-label="App wechseln"
      :aria-expanded="open"
      @click.stop="open = !open"
    >
      <AppSwitchMark />
    </button>
    <div v-if="open" class="hero-switch__menu" role="menu" aria-label="Apps">
      <a
        v-for="app in choices"
        :key="app.id"
        class="hero-switch__choice"
        :class="{ 'is-current': app.current }"
        role="menuitem"
        :href="app.current ? undefined : app.href"
        @click="app.current ? close() : null"
      >
        <img class="hero-switch__logo" :src="app.logo" :alt="app.label">
      </a>
    </div>
  </div>
</template>

<style scoped>
.hero-switch {
  position: relative;
  flex: none;
}

.hero-switch__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.85rem;
  height: 2.15rem;
  padding: 0;
  border: 1px solid var(--liquid-border-soft);
  border-radius: 0.7rem;
  background: color-mix(in srgb, #fff 72%, transparent);
  color: var(--color-text);
  cursor: pointer;
}

.hero-switch__btn :deep(.app-switch-mark) {
  width: 1.7rem;
  height: 1.05rem;
}

.hero-switch__btn:hover,
.hero-switch__btn[aria-expanded='true'] {
  color: var(--color-text);
  border-color: color-mix(in srgb, var(--color-border) 60%, var(--color-accent));
}

.hero-switch__menu {
  position: absolute;
  z-index: 30;
  top: calc(100% + 0.4rem);
  right: 0;
  width: 11.5rem;
  padding: 0.35rem;
  border: 1px solid var(--liquid-border);
  border-radius: var(--radius-lg);
  background: var(--liquid-popover-fill, #fff);
  box-shadow: var(--shadow-lg);
}

.hero-switch__choice {
  display: flex;
  align-items: center;
  min-height: 2.6rem;
  padding: 0.35rem 0.55rem;
  border-radius: 0.7rem;
  text-decoration: none;
}

.hero-switch__logo {
  display: block;
  height: 1.45rem;
  width: auto;
  max-width: 100%;
  object-fit: contain;
  object-position: left center;
}

.hero-switch__choice:hover {
  background: var(--color-bg-hover);
}

.hero-switch__choice.is-current {
  background: color-mix(in srgb, var(--color-accent) 16%, transparent);
}
</style>
