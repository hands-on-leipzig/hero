import { createRouter, createWebHistory } from 'vue-router'
import { initKeycloak } from '@/auth/keycloak'

const routes = [
  {
    path: '/',
    component: () => import('@/layouts/PublicLayout.vue'),
    children: [
      {
        path: '',
        name: 'home',
        component: () => import('@/views/HomeView.vue'),
      },
      {
        path: 'events',
        name: 'events',
        component: () => import('@/views/EventsView.vue'),
      },
      {
        path: 'events/e/:publicPath(.*)',
        name: 'events-event',
        component: () => import('@/views/PublicEventView.vue'),
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

let keycloakReady = false

router.beforeEach(async () => {
  if (!keycloakReady) {
    try {
      await initKeycloak({ onLoad: 'check-sso' })
    } catch (e) {
      console.error('Keycloak init failed', e)
    }
    keycloakReady = true
  }
  return true
})

export default router
