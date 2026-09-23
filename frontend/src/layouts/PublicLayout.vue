<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter, RouterLink, RouterView } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { theme, setTheme } from '@hands-on/glass/theme'
import AppShell from '@hands-on/glass/app-shell'
import SidebarFooter from '@hands-on/glass/sidebar-footer'
import {
  accessDenied,
  authenticated,
  getUserProfile,
  isHeroAdmin,
  login,
  logout,
  openAccount,
  profileGaps,
} from '@/auth/keycloak'
import { setLocale } from '@/i18n'
import ProfileNudge from '@/components/ProfileNudge.vue'
import logoHero from '@/assets/HERO_v1.1.png'
import logoFll from '@/assets/FIRSTLego_IconVert_RGB.png'
import logoHot from '@/assets/hot.png'

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()

const sidebarOpen = ref(false)
const sidebarFooterRef = ref(null)
const user = computed(() => (authenticated.value ? getUserProfile() : null))
const identityLabel = computed(() => user.value?.name || t('common.volunteer'))
const needsProfile = computed(() => profileGaps.value.length > 0)

const navItems = computed(() => [
  { name: 'home', path: '/', icon: 'bi-house-fill', labelKey: 'nav.home' },
  { name: 'events', path: '/events', icon: 'bi-people-fill', labelKey: 'nav.events' },
  ...(isHeroAdmin.value
    ? [{ name: 'admin', path: '/admin', icon: 'bi-shield-lock-fill', labelKey: 'nav.admin' }]
    : []),
])

function isActive(item) {
  if (item.name === 'events') {
    return route.name === 'events' || route.name === 'events-event'
  }
  return route.name === item.name
}

function closeSidebar() {
  sidebarOpen.value = false
}

function closeFooterMenus() {
  sidebarFooterRef.value?.closeMenus?.()
}

function doLogin() {
  closeFooterMenus()
  closeSidebar()
  login()
}

function doOpenAccount() {
  closeFooterMenus()
  closeSidebar()
  openAccount()
}

function doLogout() {
  closeFooterMenus()
  closeSidebar()
  logout()
}

function onShellOpen(open) {
  sidebarOpen.value = open
}

function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value
}

function goHome() {
  closeSidebar()
  if (route.name !== 'home') router.push({ name: 'home' })
}
</script>

<template>
  <AppShell
    :open="sidebarOpen"
    :menu-aria-label="t('common.menu')"
    collapsed-storage-key="hero-sidebar-collapsed"
    @toggle="toggleSidebar"
    @update:open="onShellOpen"
  >
    <template #brand>
      <RouterLink to="/" class="glass-sidebar__brand hero-brand" @click="goHome">
        <img class="glass-sidebar__brand-logo" :src="logoHero" alt="HERO" />
      </RouterLink>
    </template>

    <template #mobile-top>
      <RouterLink to="/" class="glass-sidebar__brand hero-brand" @click="goHome">
        <img class="glass-sidebar__brand-logo" :src="logoHero" alt="HERO" />
      </RouterLink>
    </template>

    <template #mobile-tabs="{ open, toggle }">
      <RouterLink
        to="/"
        class="glass-app__mobile-tab"
        :class="{ 'glass-app__mobile-tab--active': route.name === 'home' }"
        @click="closeSidebar"
      >
        <i class="bi bi-house-fill" aria-hidden="true" />
        <span class="glass-app__mobile-tab-label">{{ t('nav.tabHome') }}</span>
      </RouterLink>
      <RouterLink
        to="/events"
        class="glass-app__mobile-tab"
        :class="{ 'glass-app__mobile-tab--active': route.name === 'events' || route.name === 'events-event' }"
        @click="closeSidebar"
      >
        <i class="bi bi-people-fill" aria-hidden="true" />
        <span class="glass-app__mobile-tab-label">{{ t('nav.tabEvents') }}</span>
      </RouterLink>
      <button
        type="button"
        class="glass-app__mobile-tab glass-app__mobile-tab--more"
        :class="{ 'glass-app__mobile-tab--active': open }"
        :aria-pressed="open"
        :aria-label="t('common.menu')"
        @click="toggle"
      >
        <i class="bi" :class="open ? 'bi-x-lg' : 'bi-list'" aria-hidden="true" />
        <span class="glass-app__mobile-tab-label">{{ t('nav.tabMore') }}</span>
      </button>
    </template>

    <template #nav>
      <RouterLink
        v-for="item in navItems"
        :key="item.name"
        :to="item.path"
        class="glass-sidebar__item"
        :class="{ 'glass-sidebar__item--active': isActive(item) }"
        @click="closeSidebar"
      >
        <span class="glass-sidebar__item-icon">
          <i class="bi" :class="item.icon" aria-hidden="true" />
        </span>
        <span class="glass-sidebar__item-label">{{ t(item.labelKey) }}</span>
      </RouterLink>
    </template>

    <template #lower>
      <SidebarFooter
        ref="sidebarFooterRef"
        :hide-identity="!authenticated"
        :identity-aria-label="identityLabel"
        :settings-aria-label="t('common.settings')"
      >
        <template v-if="!authenticated" #prepend>
          <button
            v-if="accessDenied"
            type="button"
            class="sidebar-login-btn glass-sidebar__item"
            @click="doLogout"
          >
            <span class="glass-sidebar__item-icon">
              <i class="bi bi-box-arrow-right" aria-hidden="true" />
            </span>
            <span class="glass-sidebar__item-label">{{ t('auth.logout') }}</span>
          </button>
          <button
            v-else
            type="button"
            class="sidebar-login-btn glass-sidebar__item"
            @click="doLogin"
          >
            <span class="glass-sidebar__item-icon">
              <i class="bi bi-box-arrow-in-right" aria-hidden="true" />
            </span>
            <span class="glass-sidebar__item-label">{{ t('nav.login') }}</span>
          </button>
        </template>

        <template #identity="{ close }">
          <div class="glass-sidebar-footer__menu-header">
            <span class="glass-sidebar-footer__menu-title">{{ identityLabel }}</span>
          </div>
          <button
            v-if="needsProfile"
            type="button"
            class="glass-sidebar-footer__menu-item"
            role="menuitem"
            @click="doOpenAccount(); close()"
          >
            <i class="bi bi-person-gear" />
            <span>{{ t('auth.profileNudgeCta') }}</span>
          </button>
          <button
            type="button"
            class="glass-sidebar-footer__menu-item glass-sidebar-footer__menu-item--danger"
            role="menuitem"
            @click="doLogout(); close()"
          >
            <i class="bi bi-box-arrow-right" />
            <span>{{ t('auth.logout') }}</span>
          </button>
        </template>

        <template #settings>
          <div class="glass-sidebar-footer__prefs">
            <div class="glass-sidebar-footer__prefs-block">
              <span class="glass-sidebar-footer__menu-label">{{ t('common.theme') }}</span>
              <div class="glass-sidebar-footer__prefs-row" role="group" :aria-label="t('common.theme')">
                <button
                  type="button"
                  class="glass-sidebar-footer__pref-btn"
                  :class="{ active: theme === 'light' }"
                  :aria-pressed="theme === 'light'"
                  @click="setTheme('light')"
                >
                  <i class="bi bi-sun-fill" aria-hidden="true" />
                  <span>{{ t('common.light') }}</span>
                </button>
                <button
                  type="button"
                  class="glass-sidebar-footer__pref-btn"
                  :class="{ active: theme === 'dark' }"
                  :aria-pressed="theme === 'dark'"
                  @click="setTheme('dark')"
                >
                  <i class="bi bi-moon-fill" aria-hidden="true" />
                  <span>{{ t('common.dark') }}</span>
                </button>
              </div>
            </div>
            <div class="glass-sidebar-footer__prefs-block">
              <span class="glass-sidebar-footer__menu-label">{{ t('common.language') }}</span>
              <div class="glass-sidebar-footer__prefs-row" role="group" :aria-label="t('common.language')">
                <button
                  type="button"
                  class="glass-sidebar-footer__pref-btn"
                  :class="{ active: locale === 'de' }"
                  :aria-pressed="locale === 'de'"
                  @click="setLocale('de')"
                >
                  DE
                </button>
                <button
                  type="button"
                  class="glass-sidebar-footer__pref-btn"
                  :class="{ active: locale === 'en' }"
                  :aria-pressed="locale === 'en'"
                  @click="setLocale('en')"
                >
                  EN
                </button>
              </div>
            </div>
          </div>
        </template>

        <template #partners>
          <img
            :src="logoFll"
            alt="FIRST LEGO League"
            class="glass-sidebar__partner-logo glass-sidebar__partner-logo--primary"
            decoding="async"
          >
          <a
            href="https://www.hands-on-technology.org"
            target="_blank"
            rel="noopener noreferrer"
            class="glass-sidebar__partner-link"
          >
            <img
              :src="logoHot"
              alt="HANDS on TECHNOLOGY"
              class="glass-sidebar__partner-logo glass-sidebar__partner-logo--secondary"
            >
          </a>
        </template>
      </SidebarFooter>
    </template>

    <div class="glass-app__panel" :class="{ 'glass-app__panel--embed': route.name === 'events-event' }">
      <div class="glass-app__panel-body">
        <ProfileNudge v-if="route.name !== 'events-event'" />
        <RouterView />
      </div>
    </div>
  </AppShell>
</template>

<style scoped>
.sidebar-login-btn {
  width: 100%;
  max-width: 100%;
  margin: 0;
  justify-content: flex-start;
  border: 1px solid var(--color-border);
  background: var(--color-accent-soft);
  color: var(--color-accent);
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  font-size: var(--text-sm);
  border-radius: var(--radius);
  padding: 0.55rem 0.65rem;
  min-height: var(--touch);
}

.sidebar-login-btn:hover {
  background: var(--color-bg-hover);
  border-color: var(--color-accent);
}

.sidebar-login-btn .glass-sidebar__item-icon .bi {
  font-size: 1.2rem;
}
</style>
