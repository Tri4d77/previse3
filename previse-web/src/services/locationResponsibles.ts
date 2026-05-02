import api from './api'

export interface ResponsibleUser {
  id: number
  name: string
  email: string
  phone: string | null
  avatar_url: string | null
}

export interface ResponsibleRole {
  id: number
  name: string
  slug: string
}

export interface LocationResponsible {
  id: number // membership id
  user: ResponsibleUser | null
  role: ResponsibleRole | null
  assigned_at: string | null
}

export async function fetchResponsibles(locationId: number): Promise<LocationResponsible[]> {
  const r = await api.get(`/locations/${locationId}/responsibles`)
  return r.data.data
}

export async function fetchAvailableResponsibles(
  locationId: number,
): Promise<LocationResponsible[]> {
  const r = await api.get(`/locations/${locationId}/responsibles/available`)
  return r.data.data
}

export async function addResponsibles(
  locationId: number,
  membershipIds: number[],
): Promise<LocationResponsible[]> {
  const r = await api.post(`/locations/${locationId}/responsibles`, {
    membership_ids: membershipIds,
  })
  return r.data.data
}

export async function removeResponsible(
  locationId: number,
  membershipId: number,
): Promise<void> {
  await api.delete(`/locations/${locationId}/responsibles/${membershipId}`)
}
