import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // ========== AUTH (nem bejelentkezett) ==========
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/auth/LoginPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/pages/auth/ForgotPasswordPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/invitation/:token',
      name: 'accept-invitation',
      component: () => import('@/pages/auth/AcceptInvitationPage.vue'),
      meta: { guest: true },
    },

    // ========== APP (bejelentkezett) ==========
    {
      path: '/',
      component: () => import('@/layouts/MainLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/pages/dashboard/DashboardPage.vue'),
        },
        // Admin
        {
          path: 'admin/users',
          name: 'admin-users',
          component: () => import('@/pages/admin/UsersPage.vue'),
          meta: { permission: 'users.read' },
        },
        {
          path: 'admin/roles',
          name: 'admin-roles',
          component: () => import('@/pages/admin/RolesPage.vue'),
          meta: { permission: 'users.manage_roles' },
        },
        {
          path: 'admin/organization',
          name: 'admin-organization',
          component: () => import('@/pages/admin/OrganizationPage.vue'),
          meta: { permission: 'settings.manage_organization' },
        },
        // Profil
        {
          path: 'profile',
          name: 'profile',
          component: () => import('@/pages/profile/ProfilePage.vue'),
        },
      ],
    },

    // 404
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
})

// Navigáció őr (auth ellenőrzés)
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Ha auth szükséges és nincs token
  if (to.meta.requiresAuth && !authStore.token) {
    return next({ name: 'login' })
  }

  // Ha auth szükséges, de nincs user betöltve (pl. oldal frissítés után)
  if (to.meta.requiresAuth && authStore.token && !authStore.user) {
    try {
      await authStore.fetchUser()
    } catch {
      authStore.clearAuth()
      return next({ name: 'login' })
    }
  }

  // Ha guest oldal, de be van jelentkezve
  if (to.meta.guest && authStore.isAuthenticated) {
    return next({ name: 'dashboard' })
  }

  // Jogosultság ellenőrzés
  if (to.meta.permission && !authStore.hasPermission(to.meta.permission as string)) {
    return next({ name: 'dashboard' })
  }

  next()
})

export default router
