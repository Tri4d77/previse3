import api from './api'

export interface FloorItem {
  id: number
  location_id: number
  name: string
  level: number
  description: string | null
  floor_plan_path: string | null
  sort_order: number
  rooms_count?: number
  created_at: string
  updated_at: string
}

export interface FloorPayload {
  name: string
  level?: number
  description?: string | null
  sort_order?: number
}

export async function fetchFloors(locationId: number): Promise<FloorItem[]> {
  const response = await api.get(`/locations/${locationId}/floors`)
  return response.data.data
}

export async function createFloor(locationId: number, payload: FloorPayload): Promise<FloorItem> {
  const response = await api.post(`/locations/${locationId}/floors`, payload)
  return response.data.data
}

export async function updateFloor(floorId: number, payload: Partial<FloorPayload>): Promise<FloorItem> {
  const response = await api.put(`/floors/${floorId}`, payload)
  return response.data.data
}

export async function deleteFloor(floorId: number): Promise<void> {
  await api.delete(`/floors/${floorId}`)
}
