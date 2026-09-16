<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { authenticated, getUserProfile, login } from '@/auth/keycloak'
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
          <div class="welcome-actions">
            <RouterLink class="btn btn-primary" to="/events">
              <i class="bi bi-people-fill" aria-hidden="true" />
              {{ t('home.ctaEvents') }}
            </RouterLink>
            <button v-if="!authenticated" type="button" class="btn btn-secondary" @click="login">
              <i class="bi bi-box-arrow-in-right" aria-hidden="true" />
              {{ t('auth.signInWithSso') }}
            </button>
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

    <section class="welcome-section" aria-labelledby="next-title">
      <h2 id="next-title">{{ t('home.nextTitle') }}</h2>
      <div class="welcome-next">
        <article class="hero-card liquid-surface-inner">
          <h3>
            <i class="bi bi-geo-alt-fill" aria-hidden="true" />
            {{ t('home.nextFindTitle') }}
          </h3>
          <p>{{ t('home.nextFindText') }}</p>
        </article>
        <article class="hero-card liquid-surface-inner">
          <h3>
            <i class="bi bi-person-check-fill" aria-hidden="true" />
            {{ t('home.nextAccountTitle') }}
          </h3>
          <p>{{ t('home.nextAccountText') }}</p>
        </article>
      </div>
    </section>
  </div>
</template>

<style scoped>
.welcome-hero {
  padding: 1.85rem 1.5rem 1.7rem;
  margin-bottom: 1.75rem;
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
  margin: 1rem 0 0;
  font-weight: 600;
}

.welcome-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  margin-top: 1.25rem;
}

.welcome-partner {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin: 1.25rem 0 0;
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
  height: 7.5rem;
  width: auto;
  object-fit: contain;
}

.welcome-section h2 {
  margin: 0 0 0.85rem;
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.welcome-next {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.85rem;
}

.welcome-next h3 {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin: 0 0 0.45rem;
  font-size: 1.05rem;
  font-weight: 700;
}

.welcome-next h3 .bi {
  color: var(--color-accent);
}

.welcome-next p {
  margin: 0;
  color: var(--color-text-muted);
  line-height: 1.5;
  font-size: var(--text-sm);
}

@media (max-width: 860px) {
  .welcome-next {
    grid-template-columns: 1fr;
  }
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
