<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { accessDenied, authenticated, getUserProfile, login, logout } from '@/auth/keycloak'
import SharePointDocumentsCard from '@/components/SharePointDocumentsCard.vue'
import logoFll from '@/assets/FIRSTLego_IconVert_RGB.png'
import logoHot from '@/assets/hot.png'

const { t } = useI18n()
const user = computed(() => (authenticated.value ? getUserProfile() : null))
</script>

<template>
  <div class="hero-page welcome liquid-surface-scope">
    <section class="welcome-hero liquid-surface liquid-surface--accent">
      <div class="welcome-hero__layout">
        <div>
          <p class="welcome-kicker">{{ t('home.greeting') }}</p>
          <h1>{{ t('home.tagline') }}</h1>
          <p class="hero-lead">{{ t('home.intro') }}</p>
          <p v-if="authenticated" class="welcome-signed-in">
            {{ t('home.signedIn', { name: user?.name || t('common.volunteer') }) }}
          </p>
          <p v-if="accessDenied" class="welcome-no-access">
            {{ t('auth.noAccess') }}
          </p>
          <div class="welcome-actions">
            <button
              v-if="accessDenied"
              type="button"
              class="btn btn-secondary"
              @click="logout()"
            >
              <i class="bi bi-box-arrow-right" aria-hidden="true" />
              {{ t('auth.logout') }}
            </button>
            <button
              v-else-if="!authenticated"
              type="button"
              class="btn btn-primary"
              @click="login()"
            >
              <i class="bi bi-box-arrow-in-right" aria-hidden="true" />
              {{ t('auth.signInWithSso') }}
            </button>
            <RouterLink
              to="/events"
              class="btn"
              :class="authenticated ? 'btn-primary' : 'btn-secondary'"
            >
              {{ t('home.ctaEvents') }}
            </RouterLink>
          </div>
          <p class="welcome-partner">
            <span>{{ t('common.organizedBy') }}</span>
            <a href="https://www.hands-on-technology.org" target="_blank" rel="noopener noreferrer">
              <img :src="logoHot" alt="HANDS on TECHNOLOGY" class="welcome-partner__logo" />
            </a>
          </p>
        </div>
        <div class="welcome-hero__mark" aria-hidden="true">
          <img :src="logoFll" alt="" class="welcome-hero__fll" decoding="async" />
        </div>
      </div>
    </section>

    <SharePointDocumentsCard v-if="authenticated" />
  </div>
</template>

<style scoped>
.welcome {
  max-width: 72rem;
  margin: 0 auto;
  padding: 1.5rem 1.25rem 3rem;
}

.welcome-hero {
  padding: 1.5rem 1.35rem 1.35rem;
  margin-bottom: 1.25rem;
}

.welcome-hero__layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 1.5rem;
  align-items: center;
}

.welcome-kicker {
  margin: 0 0 0.35rem;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--color-accent);
}

.welcome-signed-in {
  margin: 0.85rem 0 0;
  font-weight: 600;
}

.welcome-no-access {
  margin: 0.85rem 0 0;
  font-weight: 600;
}

.welcome-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  margin: 1rem 0 0;
}

.welcome-partner {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin: 1rem 0 0;
  font-size: 0.68rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-text-subtle);
}

.welcome-partner__logo {
  height: 1.7rem;
  width: auto;
}

.welcome-hero__fll {
  height: 5.5rem;
  width: auto;
  object-fit: contain;
}

@media (max-width: 720px) {
  .welcome-hero__layout {
    grid-template-columns: 1fr;
  }

  .welcome-hero__mark {
    display: none;
  }
}
</style>
