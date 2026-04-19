import api from './api'

export interface AuthEventItem {
  id: number
  user_id: number | null
  email: string | null
  event: string
  ip_address: string | null
  user_agent: string | null
  metadata: Record<string, unknown> | null
  created_at: string
}

export interface AuthEventsResponse {
  data: AuthEventItem[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export async function fetchLoginHistory(params: {
  page?: number
  per_page?: number
  event?: string[]
} = {}): Promise<AuthEventsResponse> {
  const response = await api.get('/profile/login-history', { params })
  return response.data
}
