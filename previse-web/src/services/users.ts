import api from './api'
import type { User, PaginatedResponse } from '@/types'

// ========== TÍPUSOK ==========

export interface UsersListParams {
  page?: number
  per_page?: number
  search?: string
  role?: string
  group_id?: number
  is_active?: boolean
  organization_id?: number
  sort?: string
  order?: 'asc' | 'desc'
}

export interface SimpleOrganization {
  id: number
  parent_id: number | null
  type: 'platform' | 'subscriber' | 'client'
  name: string
  slug: string
  is_active: boolean
}

export interface InviteUserPayload {
  name: string
  email: string
  role_id: number
  group_ids?: number[]
  phone?: string
}

export interface InviteUserResponse {
  data: User
  message: string
  invitation_url: string
}

export interface UpdateUserPayload {
  name?: string
  phone?: string | null
  role_id?: number
  group_ids?: number[]
}

// ========== API HÍVÁSOK ==========

/**
 * Felhasználók listájának lekérése szűrőkkel, lapozással.
 */
export async function fetchUsers(params: UsersListParams = {}): Promise<PaginatedResponse<User>> {
  const response = await api.get<PaginatedResponse<User>>('/users', { params })
  return response.data
}

/**
 * Egy felhasználó adatai.
 */
export async function fetchUser(id: number): Promise<User> {
  const response = await api.get(`/users/${id}`)
  return response.data.data
}

/**
 * Új felhasználó meghívása.
 */
export async function inviteUser(payload: InviteUserPayload): Promise<InviteUserResponse> {
  const response = await api.post<InviteUserResponse>('/users', payload)
  return response.data
}

/**
 * Felhasználó módosítása.
 */
export async function updateUser(id: number, payload: UpdateUserPayload): Promise<User> {
  const response = await api.put(`/users/${id}`, payload)
  return response.data.data
}

/**
 * Felhasználó aktiválása / deaktiválása.
 */
export async function toggleUserActive(id: number): Promise<User> {
  const response = await api.patch(`/users/${id}/toggle-active`)
  return response.data.data
}

/**
 * Felhasználó törlése (soft delete).
 */
export async function deleteUser(id: number): Promise<void> {
  await api.delete(`/users/${id}`)
}

// ========== SZEREPKÖRÖK ÉS CSOPORTOK (a legördülőkhöz) ==========

export interface SimpleRole {
  id: number
  name: string
  slug: string
  users_count?: number
}

export interface SimpleGroup {
  id: number
  name: string
}

/**
 * Szerepkörök listája (az invite modal legördülőjéhez).
 */
export async function fetchRoles(): Promise<SimpleRole[]> {
  const response = await api.get('/roles')
  return response.data.data
}

/**
 * Szervezetek listája (szűrőkhöz).
 */
export async function fetchOrganizations(): Promise<SimpleOrganization[]> {
  const response = await api.get('/organizations')
  return response.data.data
}
