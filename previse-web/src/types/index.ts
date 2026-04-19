// ========== Auth ==========
export interface LoginCredentials {
  email: string
  password: string
}

export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  avatar_url: string | null
  initials: string
  is_active: boolean
  email_verified_at: string | null
  pending_email: string | null
  two_factor_enabled: boolean
  last_login_at: string | null
  created_at: string
  settings?: UserSettings | null
}

export interface UserSettings {
  theme: 'light' | 'dark' | 'system'
  color_scheme: string
  locale: string
  timezone: string
  items_per_page: number
  default_organization_id: number | null
  lockscreen_timeout_minutes: number
  notification_email: boolean
  notification_push: boolean
  notification_sound: boolean
}

// ========== Membership / Organization ==========
export interface Role {
  id: number
  name: string
  slug: string
  description?: string
  is_system?: boolean
  users_count?: number
  permissions?: string[]
}

export interface Organization {
  id: number
  parent_id?: number | null
  name: string
  type: 'platform' | 'subscriber' | 'client'
  slug: string
  is_active?: boolean
}

export interface OrganizationNode extends Organization {
  children: OrganizationNode[]
}

export interface Membership {
  id: number
  is_active?: boolean
  joined_at?: string | null
  last_active_at?: string | null
  organization: Organization
  role: Role
  permissions?: string[]
}

// ========== Login response-ok ==========

/**
 * Direkt belépés (1 tagság vagy default org).
 */
export interface LoginDirectResponse {
  data: {
    user: User
    current_membership: Membership
    token: string
  }
}

/**
 * Szervezet-választó szükséges.
 */
export interface LoginSelectionResponse {
  requires_organization_selection: true
  selection_token: string
  memberships: Membership[]
}

/**
 * 2FA challenge szükséges (a user-nél aktív a kétfaktoros hitelesítés).
 */
export interface LoginTwoFactorResponse {
  requires_two_factor: true
  challenge_token: string
}

export type LoginResponse = LoginDirectResponse | LoginSelectionResponse | LoginTwoFactorResponse

/**
 * /auth/user (bejelentkezett állapot).
 */
export interface AuthUserResponse {
  data: {
    user: User
    current_membership: Membership | null
    context_organization: Organization | null
    is_super_admin_impersonation: boolean
    is_super_admin: boolean
    memberships: Membership[]
    permissions: string[]
  }
}

// ========== Group ==========
export interface Group {
  id: number
  name: string
  description?: string
}

// ========== Permission ==========
export interface Permission {
  id: number
  action: string
  key: string
  description: string
}

export interface PermissionsByModule {
  [module: string]: Permission[]
}

// ========== API Pagination ==========
export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
  }
  links: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
}

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}
