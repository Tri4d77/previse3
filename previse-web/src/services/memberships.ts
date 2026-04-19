import api from './api'
import type { PaginatedResponse } from '@/types'

// ========== TÍPUSOK ==========

export type MembershipStatus = 'active' | 'inactive' | 'pending' | 'expired' | 'deleted'

export interface MembershipUser {
  id: number
  name: string
  email: string
  phone: string | null
  initials: string
  is_active: boolean
}

export interface MembershipRole {
  id: number
  name: string
  slug: string
}

export interface MembershipOrganization {
  id: number
  name: string
  type: 'platform' | 'subscriber' | 'client'
}

export interface MembershipItem {
  id: number
  user: MembershipUser
  organization: MembershipOrganization
  role: MembershipRole
  is_active: boolean
  status: MembershipStatus
  joined_at: string | null
  last_active_at: string | null
  invitation_sent_at: string | null
  deleted_at: string | null
  created_at: string
}

export interface MembershipsListParams {
  page?: number
  per_page?: number
  search?: string
  role?: string
  status?: 'active' | 'inactive' | 'pending'
  include_deleted?: boolean
  sort?: string
  order?: 'asc' | 'desc'
}

export interface CheckEmailResponse {
  user_exists: boolean
  user?: {
    id: number
    name: string
    email: string
  }
  already_member?: boolean
  has_pending_invitation?: boolean
  has_deleted_membership?: boolean
}

export interface InvitePayload {
  name?: string
  email: string
  phone?: string
  role_id: number
}

export interface InviteResponse {
  data: MembershipItem
  message: string
  is_existing_user: boolean
  invitation_url: string
}

export interface UpdateMembershipPayload {
  name?: string
  phone?: string | null
  role_id?: number
}

export interface SimpleRole {
  id: number
  name: string
  slug: string
  description?: string | null
  is_system?: boolean
  users_count?: number
}

// ========== API HÍVÁSOK ==========

export async function fetchMemberships(params: MembershipsListParams = {}): Promise<PaginatedResponse<MembershipItem>> {
  const response = await api.get<PaginatedResponse<MembershipItem>>('/memberships', { params })
  return response.data
}

export async function checkEmail(email: string): Promise<CheckEmailResponse> {
  const response = await api.post<CheckEmailResponse>('/memberships/check-email', { email })
  return response.data
}

export async function inviteMember(payload: InvitePayload): Promise<InviteResponse> {
  const response = await api.post<InviteResponse>('/memberships', payload)
  return response.data
}

export async function updateMembership(id: number, payload: UpdateMembershipPayload): Promise<MembershipItem> {
  const response = await api.put(`/memberships/${id}`, payload)
  return response.data.data
}

export async function toggleMembershipActive(id: number): Promise<MembershipItem> {
  const response = await api.patch(`/memberships/${id}/toggle-active`)
  return response.data.data
}

export async function resendInvitation(id: number): Promise<InviteResponse> {
  const response = await api.post<InviteResponse>(`/memberships/${id}/resend-invitation`)
  return response.data
}

export async function deleteMembership(id: number): Promise<void> {
  await api.delete(`/memberships/${id}`)
}

export async function restoreMembership(id: number): Promise<InviteResponse> {
  const response = await api.post<InviteResponse>(`/memberships/${id}/restore`)
  return response.data
}

export async function fetchRoles(): Promise<SimpleRole[]> {
  const response = await api.get('/roles')
  return response.data.data
}
