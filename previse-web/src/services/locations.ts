import api from './api'

// ========== TYPES ==========

export interface LocationType {
  id: number
  name: string
  sort_order: number
}

export type LocationStatus = 0 | 1 | 2 // 0=archív, 1=aktív, 2=megszűnt

export interface LocationItem {
  id: number
  organization_id: number
  code: string
  name: string
  type: { id: number; name: string } | null
  address: string | null
  city: string | null
  zip_code: string | null
  latitude: number | null
  longitude: number | null
  description: string | null
  image_url: string | null
  thumb_url: string | null
  is_active: LocationStatus
  is_deleted: boolean
  tags: { id: number; name: string; color: string; sort_order: number }[]
  created_at: string
  updated_at: string
}

export interface LocationsListParams {
  search?: string
  type_id?: number
  is_active?: 'active' | 'all' | '0' | '1' | '2'
  include_deleted?: boolean
  sort?: 'name' | 'code' | 'city' | 'created_at' | 'updated_at'
  order?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export interface LocationsListResponse {
  data: LocationItem[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
  }
}

export interface CreateLocationPayload {
  code?: string
  name: string
  type_id?: number | null
  address?: string | null
  city?: string | null
  zip_code?: string | null
  latitude?: number | null
  longitude?: number | null
  description?: string | null
}

export type UpdateLocationPayload = Partial<CreateLocationPayload>

// ========== API ==========

export async function fetchLocations(params: LocationsListParams = {}): Promise<LocationsListResponse> {
  const response = await api.get<LocationsListResponse>('/locations', { params })
  return response.data
}

export async function fetchLocation(id: number): Promise<LocationItem> {
  const response = await api.get(`/locations/${id}`)
  return response.data.data
}

export async function createLocation(payload: CreateLocationPayload): Promise<LocationItem> {
  const response = await api.post('/locations', payload)
  return response.data.data
}

export async function updateLocation(id: number, payload: UpdateLocationPayload): Promise<LocationItem> {
  const response = await api.put(`/locations/${id}`, payload)
  return response.data.data
}

export async function deleteLocation(id: number): Promise<void> {
  await api.delete(`/locations/${id}`)
}

export async function restoreLocation(id: number): Promise<LocationItem> {
  const response = await api.post(`/locations/${id}/restore`)
  return response.data.data
}

export async function setLocationStatus(id: number, isActive: LocationStatus): Promise<LocationItem> {
  const response = await api.post(`/locations/${id}/status`, { is_active: isActive })
  return response.data.data
}

export async function uploadLocationImage(id: number, file: File): Promise<LocationItem> {
  const form = new FormData()
  form.append('image', file)
  const response = await api.post(`/locations/${id}/image`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return response.data.data
}

export async function deleteLocationImage(id: number): Promise<LocationItem> {
  const response = await api.delete(`/locations/${id}/image`)
  return response.data.data
}

// ========== TYPES API ==========

export async function fetchLocationTypes(): Promise<LocationType[]> {
  const response = await api.get('/location-types')
  return response.data.data
}

export async function createLocationType(name: string): Promise<LocationType> {
  const response = await api.post('/location-types', { name })
  return response.data.data
}

export async function updateLocationType(id: number, name: string): Promise<LocationType> {
  const response = await api.put(`/location-types/${id}`, { name })
  return response.data.data
}

export async function deleteLocationType(id: number): Promise<void> {
  await api.delete(`/location-types/${id}`)
}
