// ========== Auth ==========
export interface LoginCredentials {
  email: string
  password: string
}

export interface LoginResponse {
  data: {
    user: User
    token: string
  }
}

// ========== User ==========
export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  avatar_url: string | null
  initials: string
  is_active: boolean
  two_factor_enabled: boolean
  last_login_at: string | null
  created_at: string
  role: Role
  organization: Organization
  permissions?: string[]
  settings?: UserSettings
  groups?: Group[]
}

export interface UserSettings {
  theme: 'light' | 'dark' | 'system'
  color_scheme: string
  locale: string
  timezone: string
  items_per_page: number
  default_page: string
  notification_email: boolean
  notification_push: boolean
  notification_sound: boolean
}

// ========== Role ==========
export interface Role {
  id: number
  name: string
  slug: string
  description?: string
  is_system?: boolean
  users_count?: number
  permissions?: string[]
}

// ========== Organization ==========
export interface Organization {
  id: number
  name: string
  type: 'platform' | 'subscriber' | 'client'
  slug: string
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

// ========== API Error ==========
export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}
