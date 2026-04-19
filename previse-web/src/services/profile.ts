import api from './api'

export interface SessionItem {
  id: number
  name: string
  ip_address: string | null
  user_agent: string | null
  last_used_at: string | null
  created_at: string
  expires_at: string | null
  is_current: boolean
  is_impersonation: boolean
}

export interface UpdatePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
  logout_other_devices?: boolean
}

export async function updatePassword(payload: UpdatePasswordPayload): Promise<void> {
  await api.put('/profile/password', payload)
}

export async function fetchSessions(): Promise<SessionItem[]> {
  const response = await api.get('/profile/sessions')
  return response.data.data
}

export async function revokeSession(id: number): Promise<void> {
  await api.delete(`/profile/sessions/${id}`)
}

export async function revokeOtherSessions(): Promise<{ revoked_count: number }> {
  const response = await api.delete('/profile/sessions/others')
  return response.data
}
