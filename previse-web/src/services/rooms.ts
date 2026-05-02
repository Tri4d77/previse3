import api from './api'

export interface RoomItem {
  id: number
  location_id: number
  floor_id: number | null
  name: string
  number: string | null
  type: string | null
  area_sqm: string | null  // decimal-as-string Laravel-ből
  description: string | null
  room_plan_path: string | null
  sort_order: number
  floor?: { id: number; name: string; level: number } | null
  created_at: string
  updated_at: string
}

export interface RoomPayload {
  floor_id?: number | null
  name: string
  number?: string | null
  type?: string | null
  area_sqm?: number | null
  description?: string | null
  sort_order?: number
}

export async function fetchRoomsByLocation(
  locationId: number,
  options: { floor_id?: number | 'null' } = {},
): Promise<RoomItem[]> {
  const params: Record<string, string> = {}
  if (options.floor_id !== undefined) {
    params.floor_id = String(options.floor_id)
  }
  const response = await api.get(`/locations/${locationId}/rooms`, { params })
  return response.data.data
}

export async function fetchRoomsByFloor(floorId: number): Promise<RoomItem[]> {
  const response = await api.get(`/floors/${floorId}/rooms`)
  return response.data.data
}

export async function fetchRoomTypes(locationId: number): Promise<string[]> {
  const response = await api.get(`/locations/${locationId}/room-types`)
  return response.data.data
}

export async function createRoom(locationId: number, payload: RoomPayload): Promise<RoomItem> {
  const response = await api.post(`/locations/${locationId}/rooms`, payload)
  return response.data.data
}

export async function updateRoom(roomId: number, payload: Partial<RoomPayload>): Promise<RoomItem> {
  const response = await api.put(`/rooms/${roomId}`, payload)
  return response.data.data
}

export async function deleteRoom(roomId: number): Promise<void> {
  await api.delete(`/rooms/${roomId}`)
}
