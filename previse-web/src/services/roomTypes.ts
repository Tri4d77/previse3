import api from './api'

export interface RoomTypeItem {
  id: number
  name: string
  sort_order: number
}

export async function fetchRoomTypeCatalog(): Promise<RoomTypeItem[]> {
  const response = await api.get('/room-types')
  return response.data.data
}

export async function createRoomType(name: string): Promise<RoomTypeItem> {
  const response = await api.post('/room-types', { name })
  return response.data.data
}

export async function updateRoomType(id: number, name: string): Promise<RoomTypeItem> {
  const response = await api.put(`/room-types/${id}`, { name })
  return response.data.data
}

export async function deleteRoomType(id: number): Promise<void> {
  await api.delete(`/room-types/${id}`)
}
