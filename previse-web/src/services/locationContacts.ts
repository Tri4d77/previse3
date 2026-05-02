import api from './api'

export interface LocationContact {
  id: number
  location_id: number
  name: string
  role_label: string | null
  phone: string | null
  email: string | null
  note: string | null
  sort_order: number
  created_at: string
  updated_at: string
}

export interface LocationContactPayload {
  name: string
  role_label?: string | null
  phone?: string | null
  email?: string | null
  note?: string | null
  sort_order?: number
}

export async function fetchContacts(locationId: number): Promise<LocationContact[]> {
  const r = await api.get(`/locations/${locationId}/contacts`)
  return r.data.data
}

export async function fetchContactRoles(locationId: number): Promise<string[]> {
  const r = await api.get(`/locations/${locationId}/contact-roles`)
  return r.data.data
}

export async function createContact(
  locationId: number,
  payload: LocationContactPayload,
): Promise<LocationContact> {
  const r = await api.post(`/locations/${locationId}/contacts`, payload)
  return r.data.data
}

export async function updateContact(
  contactId: number,
  payload: Partial<LocationContactPayload>,
): Promise<LocationContact> {
  const r = await api.put(`/location-contacts/${contactId}`, payload)
  return r.data.data
}

export async function deleteContact(contactId: number): Promise<void> {
  await api.delete(`/location-contacts/${contactId}`)
}
