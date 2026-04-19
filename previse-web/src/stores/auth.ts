import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import type {
  User,
  Membership,
  Organization,
  LoginCredentials,
  LoginResponse,
} from '@/types'

export const useAuthStore = defineStore('auth', () => {
  // ========== STATE ==========
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const currentMembership = ref<Membership | null>(null)
  const contextOrganization = ref<Organization | null>(null) // szuper-admin impersonation esetén
  const isSuperAdmin = ref<boolean>(false)
  const isImpersonation = ref<boolean>(false)
  const memberships = ref<Membership[]>([])
  const permissions = ref<string[]>([])
  const loading = ref(false)
  const isLocked = ref<boolean>(localStorage.getItem('is_locked') === 'true')

  // A login után, ha szervezet-választó szükséges, itt tároljuk a selection tokent és a választható tagságokat
  const selectionToken = ref<string | null>(null)
  const selectionMemberships = ref<Membership[]>([])

  // ========== GETTERS ==========
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const needsOrganizationSelection = computed(() => !!selectionToken.value)

  /**
   * Hozzáférhet-e a user a szervezet-kezeléshez?
   * - Szuper-admin: mindig
   * - Subscriber admin: igen (saját + ügyfél-szervezetek kezeléséhez)
   */
  const canManageOrganizations = computed(() => {
    if (isSuperAdmin.value) return true
    if (isImpersonation.value) return true // impersonation alatt a szuper-admin jogaival fut
    if (!currentMembership.value) return false
    return currentMembership.value.organization.type === 'subscriber'
      && currentMembership.value.role.slug === 'admin'
  })

  const userName = computed(() => user.value?.name || '')
  const userEmail = computed(() => user.value?.email || '')
  const userInitials = computed(() => user.value?.initials || '')

  // Aktuális szervezet neve (normál vagy impersonation)
  const currentOrganizationName = computed(() => {
    if (contextOrganization.value) return contextOrganization.value.name
    return currentMembership.value?.organization.name || ''
  })
  const currentOrganizationType = computed(() => {
    if (contextOrganization.value) return contextOrganization.value.type
    return currentMembership.value?.organization.type || ''
  })
  const currentRoleName = computed(() => {
    if (isImpersonation.value) return 'Szuper-admin'
    return currentMembership.value?.role.name || ''
  })

  // ========== ACTIONS ==========

  /**
   * Bejelentkezés.
   */
  async function login(credentials: LoginCredentials): Promise<LoginResponse> {
    loading.value = true
    try {
      const response = await api.post<LoginResponse>('/auth/login', {
        ...credentials,
        device_name: 'Web Browser',
      })

      const data = response.data

      if ('requires_organization_selection' in data) {
        // Szervezet-választó szükséges
        selectionToken.value = data.selection_token
        selectionMemberships.value = data.memberships
        // Átmeneti token beállítás a selection-höz
        localStorage.setItem('auth_token', data.selection_token)
        token.value = data.selection_token
      } else {
        // Direkt belépés
        applyAuthData(data.data.user, data.data.current_membership, data.data.token, null, false)
        // Memberships lista, permissions, is_super_admin frissítése
        await fetchUser()
      }

      return data
    } finally {
      loading.value = false
    }
  }

  /**
   * Szervezet-választás (szervezet-választó oldalról).
   */
  async function selectOrganization(membershipId: number): Promise<void> {
    const response = await api.post('/auth/select-organization', {
      membership_id: membershipId,
    })
    const data = response.data.data
    applyAuthData(data.user, data.current_membership, data.token, null, false)
    selectionToken.value = null
    selectionMemberships.value = []
    // Teljes state frissítés (memberships lista, is_super_admin, stb.)
    await fetchUser()
  }

  /**
   * Szervezet-váltás bejelentkezett állapotban.
   */
  async function switchOrganization(membershipId: number): Promise<void> {
    const response = await api.post('/auth/switch-organization', {
      membership_id: membershipId,
    })
    const data = response.data.data
    applyAuthData(data.user, data.current_membership, data.token, null, false)
    await fetchUser()
  }

  /**
   * Szuper-admin belépés egy szervezet kontextusába.
   */
  async function enterOrganization(organizationId: number): Promise<void> {
    const response = await api.post(`/auth/enter-organization/${organizationId}`)
    const data = response.data.data
    applyAuthData(data.user, null, data.token, data.context_organization, true)
    await fetchUser()
  }

  /**
   * Szuper-admin visszalép a Platform-ra.
   */
  async function exitOrganization(): Promise<void> {
    const response = await api.post('/auth/exit-organization')
    const data = response.data.data
    applyAuthData(data.user, data.current_membership, data.token, null, false)
    await fetchUser()
  }

  /**
   * Bejelentkezett user adatainak frissítése.
   */
  async function fetchUser(): Promise<void> {
    if (!token.value) return

    try {
      const response = await api.get('/auth/user')
      const data = response.data.data

      user.value = data.user
      currentMembership.value = data.current_membership
      contextOrganization.value = data.context_organization
      isSuperAdmin.value = data.is_super_admin
      isImpersonation.value = data.is_super_admin_impersonation
      memberships.value = data.memberships
      permissions.value = data.permissions
    } catch {
      clearAuth()
    }
  }

  /**
   * Kijelentkezés.
   */
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
    currentMembership.value = null
    contextOrganization.value = null
    isSuperAdmin.value = false
    isImpersonation.value = false
    memberships.value = []
    permissions.value = []
    isLocked.value = false
    selectionToken.value = null
    selectionMemberships.value = []
    localStorage.removeItem('auth_token')
    localStorage.removeItem('is_locked')
  }

  /**
   * Auth adatok beállítása (login / switch után).
   */
  function applyAuthData(
    u: User,
    membership: Membership | null,
    newToken: string,
    contextOrg: Organization | null,
    impersonation: boolean,
  ): void {
    user.value = u
    currentMembership.value = membership
    contextOrganization.value = contextOrg
    isImpersonation.value = impersonation
    token.value = newToken
    isLocked.value = false

    permissions.value = membership?.permissions || (impersonation ? ['*'] : [])

    localStorage.setItem('auth_token', newToken)
    localStorage.removeItem('is_locked')

    if (u.settings?.locale) {
      localStorage.setItem('locale', u.settings.locale)
    }
  }

  // ========== PERMISSIONS ==========

  function hasPermission(permission: string): boolean {
    if (isSuperAdmin.value || isImpersonation.value) return true
    return permissions.value.includes(permission)
  }

  function hasAnyPermission(...perms: string[]): boolean {
    if (isSuperAdmin.value || isImpersonation.value) return true
    return perms.some(p => permissions.value.includes(p))
  }

  // ========== LOCKSCREEN ==========

  function lock(): void {
    isLocked.value = true
    localStorage.setItem('is_locked', 'true')
  }

  async function verifyPassword(password: string): Promise<void> {
    await api.post('/auth/verify-password', { password })
    isLocked.value = false
    localStorage.removeItem('is_locked')
  }

  return {
    // State
    user, token, currentMembership, contextOrganization,
    isSuperAdmin, isImpersonation, memberships, permissions,
    loading, isLocked, selectionToken, selectionMemberships,
    // Getters
    isAuthenticated, needsOrganizationSelection, canManageOrganizations,
    userName, userEmail, userInitials,
    currentOrganizationName, currentOrganizationType, currentRoleName,
    // Actions
    login, selectOrganization, switchOrganization,
    enterOrganization, exitOrganization,
    fetchUser, logout, clearAuth,
    hasPermission, hasAnyPermission,
    lock, verifyPassword,
  }
})
