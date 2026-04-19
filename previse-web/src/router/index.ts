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
    {
      path: '/email/confirm/:token',
      name: 'confirm-email',
      component: () => import('@/pages/auth/ConfirmEmailPage.vue'),
      meta: { guest: true, allowAuth: true },
    },
    {
      path: '/select-organization',
      name: 'select-organization',
      component: () => import('@/pages/auth/SelectOrganizationPage.vue'),
      meta: { requiresSelection: true },
    },

    // ========== LOCKSCREEN (bejelentkezett, de z\u00e1rolt) ==========
    {
      path: '/lock',
      name: 'lock',
      component: () => import('@/pages/auth/LockScreenPage.vue'),
      meta: { requiresAuth: true, allowLocked: true },
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
        {
          path: 'admin/organizations',
          name: 'admin-organizations',
          component: () => import('@/pages/admin/OrganizationsPage.vue'),
          meta: { requiresCanManageOrganizations: true },
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

// Navigáció őr
router.beforeEach(async (to, from) => {
  const authStore = useAuthStore()

  // Szervezet-választó oldal kezelése
  if (to.meta.requiresSelection) {
    if (!authStore.selectionToken) {
      return { name: 'login' }
    }
    return true
  }

  // Ha függőben van szervezet-választás, de máshova megy
  if (authStore.needsOrganizationSelection && !to.meta.guest) {
    return { name: 'select-organization' }
  }

  // Ha auth szükséges és nincs token -> login
  if (to.meta.requiresAuth && !authStore.token) {
    return { name: 'login' }
  }

  // Ha auth szükséges, de nincs user betöltve (pl. oldal frissítés után)
  if (to.meta.requiresAuth && authStore.token && !authStore.user) {
    try {
      await authStore.fetchUser()
    } catch {
      authStore.clearAuth()
      return { name: 'login' }
    }
  }

  // Ha a felhasználó zárolva van ÉS nem a lockscreen-re megy -> lockscreen
  if (authStore.isLocked && to.name !== 'lock' && to.meta.requiresAuth) {
    return { name: 'lock' }
  }

  // Ha NINCS zárolva, de mégis a lockscreen-re megy -> dashboard
  if (!authStore.isLocked && to.name === 'lock' && authStore.isAuthenticated) {
    return { name: 'dashboard' }
  }

  // Ha guest oldal, de be van jelentkezve (kivéve ha allowAuth is van, pl. email-megerősítés)
  if (to.meta.guest && !to.meta.allowAuth && authStore.isAuthenticated && !authStore.isLocked) {
    return { name: 'dashboard' }
  }

  // Jogosultság ellenőrzés
  if (to.meta.permission && !authStore.hasPermission(to.meta.permission as string)) {
    return { name: 'dashboard' }
  }

  // Szuper-admin only route-ok
  if (to.meta.requiresSuperAdmin && !authStore.isSuperAdmin) {
    return { name: 'dashboard' }
  }

  // Szervezet-kezelés jogosultság (szuper-admin VAGY subscriber admin)
  if (to.meta.requiresCanManageOrganizations && !authStore.canManageOrganizations) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
