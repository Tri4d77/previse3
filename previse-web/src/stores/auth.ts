import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import type { User, LoginCredentials, LoginResponse } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const permissions = ref<string[]>([])
  const loading = ref(false)

  // Getters
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const userName = computed(() => user.value?.name || '')
  const userInitials = computed(() => user.value?.initials || '')
  const userRole = computed(() => user.value?.role?.name || '')
  const userRoleSlug = computed(() => user.value?.role?.slug || '')
  const organizationName = computed(() => user.value?.organization?.name || '')
  const organizationType = computed(() => user.value?.organization?.type || '')
  const isSuperAdmin = computed(() =>
    organizationType.value === 'platform' && userRoleSlug.value === 'admin'
  )

  // Actions
  async function login(credentials: LoginCredentials): Promise<void> {
    loading.value = true
    try {
      const response = await api.post<LoginResponse>('/auth/login', {
        ...credentials,
        device_name: 'Web Browser',
      })

      const data = response.data.data
      token.value = data.token
      user.value = data.user
      permissions.value = data.user.permissions || []

      localStorage.setItem('auth_token', data.token)

      // Nyelv beállítása a felhasználó preferenciája alapján
      if (data.user.settings?.locale) {
        localStorage.setItem('locale', data.user.settings.locale)
      }
    } finally {
      loading.value = false
    }
  }

  async function fetchUser(): Promise<void> {
    if (!token.value) return

    try {
      const response = await api.get('/auth/user')
      user.value = response.data.data
      permissions.value = response.data.data.permissions || []
    } catch {
      clearAuth()
    }
  }

  async function logout(): Promise<void> {
    try {
      await api.post('/auth/logout')
    } catch {
      // Hiba esetén is kiléptetjük lokálisan
    } finally {
      clearAuth()
    }
  }

  function clearAuth(): void {
    user.value = null
    token.value = null
    permissions.value = []
    localStorage.removeItem('auth_token')
  }

  function hasPermission(permission: string): boolean {
    if (isSuperAdmin.value) return true
    return permissions.value.includes(permission)
  }

  function hasAnyPermission(...perms: string[]): boolean {
    if (isSuperAdmin.value) return true
    return perms.some(p => permissions.value.includes(p))
  }

  return {
    // State
    user, token, permissions, loading,
    // Getters
    isAuthenticated, userName, userInitials, userRole, userRoleSlug,
    organizationName, organizationType, isSuperAdmin,
    // Actions
    login, fetchUser, logout, clearAuth, hasPermission, hasAnyPermission,
  }
})
