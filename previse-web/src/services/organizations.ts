import api from './api'

export type OrganizationType = 'platform' | 'subscriber' | 'client'
export type OrganizationStatus = 'active' | 'inactive' | 'terminated'

export interface OrganizationItem {
  id: number
  parent_id: number | null
  type: OrganizationType
  name: string
  slug: string
  address?: string | null
  city?: string | null
  zip_code?: string | null
  phone?: string | null
  email?: string | null
  tax_number?: string | null
  status: OrganizationStatus
  is_active: boolean
  terminated_at?: string | null
  created_at?: string | null
  stats?: {
    members_count: number
    children_count: number
  }
}

export interface OrganizationTreeNode extends OrganizationItem {
  children: OrganizationTreeNode[]
}

export interface OrganizationsListParams {
  search?: string
  type?: OrganizationType
  status?: OrganizationStatus
}

export interface CreateOrganizationPayload {
  name: string
  type: 'subscriber' | 'client'
  parent_id?: number | null
  address?: string | null
  city?: string | null
  zip_code?: string | null
  phone?: string | null
  email?: string | null
  tax_number?: string | null
}

export interface UpdateOrganizationPayload {
  name?: string
  address?: string | null
  city?: string | null
  zip_code?: string | null
  phone?: string | null
  email?: string | null
  tax_number?: string | null
}

export async function fetchOrganizationsTree(params: OrganizationsListParams = {}): Promise<OrganizationTreeNode[]> {
  const response = await api.get('/admin/organizations-tree', { params })
  return response.data.data
}

export async function fetchOrganizations(params: OrganizationsListParams = {}): Promise<OrganizationItem[]> {
  const response = await api.get('/organizations', { params })
  return response.data.data
}

export async function fetchOrganization(id: number): Promise<OrganizationItem> {
  const response = await api.get(`/organizations/${id}`)
  return response.data.data
}

export async function createOrganization(payload: CreateOrganizationPayload): Promise<OrganizationItem> {
  const response = await api.post('/organizations', payload)
  return response.data.data
}

export async function updateOrganization(id: number, payload: UpdateOrganizationPayload): Promise<OrganizationItem> {
  const response = await api.put(`/organizations/${id}`, payload)
  return response.data.data
}

export async function setOrganizationStatus(id: number, status: OrganizationStatus): Promise<OrganizationItem> {
  const response = await api.post(`/organizations/${id}/status`, { status })
  return response.data.data
}
