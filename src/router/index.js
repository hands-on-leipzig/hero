import { createRouter, createWebHistory } from 'vue-router'
import { hasOidcCallback, initKeycloak } from '@/auth/keycloak'

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

router.beforeEach(async () => {
  // Only talk to Keycloak when returning from the login redirect.
  if (hasOidcCallback()) {
    try {
      await initKeycloak()
    } catch (e) {
      console.error('Keycloak callback failed', e)
    }
  }
  return true
})

export default router
